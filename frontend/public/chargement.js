/**
 * Les deux gestes de l'écran de chargement qui demandent du script.
 *
 * Fichier séparé et non script en ligne : la politique de sécurité du contenu
 * du site n'autorise que ses propres fichiers — `script-src 'self'`. Un script
 * écrit dans le HTML serait refusé par le navigateur, et l'écran resterait
 * figé. C'est exactement le genre de règle qui ne se voit pas dans le code.
 */

(function () {
  // La paire se montre quand elle arrive. Elle est demandée en priorité basse :
  // sur une connexion lente, le paquet de la boutique passe devant, et la paire
  // peut n'arriver qu'après. La ligne de texte, elle, est là dès le début.
  var paire = document.querySelector('.rcc-paire');

  if (paire) {
    if (paire.complete && paire.naturalWidth > 0) {
      paire.classList.add('est-la');
    } else {
      paire.addEventListener('load', function () {
        paire.classList.add('est-la');
      });
    }
  }

  // Si le paquet n'arrive jamais, personne ne retirera cet écran. Plutôt que de
  // laisser tourner une barre indéfiniment devant quelqu'un, on le dit.
  setTimeout(function () {
    var mot = document.getElementById('chargement-mot');

    if (mot && document.getElementById('chargement')) {
      mot.textContent = 'La connexion semble lente — patientez ou rechargez la page';
    }
  }, 15000);
})();
