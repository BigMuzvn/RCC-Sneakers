<?php

namespace Rcc\Admin;

use Rcc\AdminLog;
use Rcc\Database;
use Rcc\Newsletter\ContactList;
use Rcc\Request;
use Rcc\Response;

/**
 * Messages de contact et inscrits à la lettre d'information.
 *
 * Les deux vivaient dans des formulaires qui ne menaient nulle part. Ils ont
 * maintenant une destination et un suivi.
 */
class InboxController
{
    private const MESSAGE_STATUSES = ['new', 'handled', 'archived'];

    public function __construct(private ContactList $contacts)
    {
    }

    // ---------------------------------------------------------- messages

    /** @param array<string,mixed> $admin */
    public function messages(Request $request, array $admin): Response
    {
        $statut = (string) $request->input('status', '');
        $where = in_array($statut, self::MESSAGE_STATUSES, true) ? ' WHERE status = ?' : '';
        $params = $where === '' ? [] : [$statut];

        $rows = Database::run(
            "SELECT id, name, email, phone, subject, body, status, created_at, handled_at
               FROM contact_messages{$where}
              ORDER BY CASE status WHEN 'new' THEN 0 ELSE 1 END, created_at DESC
              LIMIT 200",
            $params
        )->fetchAll();

        return Response::data([
            'messages' => array_map(static fn (array $r) => [
                'id' => (int) $r['id'],
                'name' => $r['name'],
                'email' => $r['email'],
                'phone' => $r['phone'],
                'subject' => $r['subject'],
                'body' => $r['body'],
                'status' => $r['status'],
                'created_at' => $r['created_at'],
                'handled_at' => $r['handled_at'],
            ], $rows),
            'counts' => $this->messageCounts(),
        ]);
    }

    /** @param array<string,mixed> $admin */
    public function updateMessageStatus(Request $request, array $admin, string $id): Response
    {
        $message = Database::first('SELECT id, subject, status FROM contact_messages WHERE id = ?', [(int) $id]);

        if ($message === null) {
            return Response::notFound("Ce message n'existe pas.");
        }

        $statut = (string) $request->input('status', '');

        if (!in_array($statut, self::MESSAGE_STATUSES, true)) {
            return Response::validation(['status' => 'Statut attendu : new, handled ou archived.']);
        }

        Database::run(
            'UPDATE contact_messages SET status = ?, handled_at = ? WHERE id = ?',
            [$statut, $statut === 'new' ? null : Database::now(), $message['id']]
        );

        AdminLog::record($admin, 'message.status', $message['subject'], $message['status'] . ' → ' . $statut);

        return Response::data(['status' => $statut, 'counts' => $this->messageCounts()]);
    }

    // -------------------------------------------------------- newsletter

    /** @param array<string,mixed> $admin */
    public function subscribers(Request $request, array $admin): Response
    {
        $search = trim((string) $request->input('search', ''));
        $where = $search === '' ? '' : ' WHERE email LIKE ?';
        $params = $search === '' ? [] : ['%' . $search . '%'];

        $rows = Database::run(
            "SELECT id, email, status, source, synced_at, created_at, unsubscribed_at
               FROM newsletter_subscribers{$where}
              ORDER BY created_at DESC LIMIT 500",
            $params
        )->fetchAll();

        return Response::data([
            'subscribers' => array_map(static fn (array $r) => [
                'id' => (int) $r['id'],
                'email' => $r['email'],
                'status' => $r['status'],
                'source' => $r['source'],
                'synced' => $r['synced_at'] !== null,
                'created_at' => $r['created_at'],
                'unsubscribed_at' => $r['unsubscribed_at'],
            ], $rows),
            'counts' => [
                'subscribed' => $this->count("SELECT COUNT(*) c FROM newsletter_subscribers WHERE status = 'subscribed'"),
                'unsubscribed' => $this->count("SELECT COUNT(*) c FROM newsletter_subscribers WHERE status = 'unsubscribed'"),
                'unsynced' => $this->count(
                    "SELECT COUNT(*) c FROM newsletter_subscribers WHERE status = 'subscribed' AND synced_at IS NULL"
                ),
            ],
        ]);
    }

    /** @param array<string,mixed> $admin */
    public function unsubscribe(Request $request, array $admin, string $id): Response
    {
        $inscrit = Database::first('SELECT id, email, status FROM newsletter_subscribers WHERE id = ?', [(int) $id]);

        if ($inscrit === null) {
            return Response::notFound("Cet inscrit n'existe pas.");
        }

        Database::run(
            "UPDATE newsletter_subscribers SET status = 'unsubscribed', unsubscribed_at = ? WHERE id = ?",
            [Database::now(), $inscrit['id']]
        );

        // Retiré de la liste du prestataire aussi, sans quoi il continuerait de
        // recevoir les campagnes — et la désinscription ne vaudrait rien.
        $this->contacts->remove($inscrit['email']);

        AdminLog::record($admin, 'newsletter.unsubscribe', $inscrit['email']);

        return Response::data(['status' => 'unsubscribed']);
    }

    /**
     * Rejoue la synchronisation des inscrits que le prestataire n'a jamais
     * reçus — ceux dont l'ajout a échoué pendant une panne.
     *
     * @param array<string,mixed> $admin
     */
    public function sync(Request $request, array $admin): Response
    {
        $enAttente = Database::run(
            "SELECT id, email FROM newsletter_subscribers
              WHERE status = 'subscribed' AND synced_at IS NULL LIMIT 200"
        )->fetchAll();

        $reussis = 0;

        foreach ($enAttente as $inscrit) {
            if (!$this->contacts->add($inscrit['email'])) {
                continue;
            }

            Database::run(
                'UPDATE newsletter_subscribers SET synced_at = ? WHERE id = ?',
                [Database::now(), $inscrit['id']]
            );

            $reussis++;
        }

        if ($reussis > 0) {
            AdminLog::record($admin, 'newsletter.sync', 'liste', sprintf('%d inscrit(s) synchronisé(s)', $reussis));
        }

        return Response::data([
            'attempted' => count($enAttente),
            'synced' => $reussis,
            'remaining' => count($enAttente) - $reussis,
        ]);
    }

    // ------------------------------------------------------------- privé

    /** @return array<string,int> */
    private function messageCounts(): array
    {
        $counts = ['new' => 0, 'handled' => 0, 'archived' => 0];

        foreach (Database::run('SELECT status, COUNT(*) c FROM contact_messages GROUP BY status')->fetchAll() as $row) {
            $counts[$row['status']] = (int) $row['c'];
        }

        return $counts;
    }

    private function count(string $sql): int
    {
        return (int) (Database::first($sql)['c'] ?? 0);
    }
}
