/**
 * Retrait de l'écran de chargement peint dans `index.html`.
 *
 * Il est posé là parce que l'essentiel de l'attente a lieu avant que React
 * existe : en 3G, deux secondes sur trois s'écoulent pendant le téléchargement
 * du paquet. Il revient donc à l'application de le retirer une fois prête —
 * « prête » voulant dire *le catalogue est arrivé*, et non *React a monté* :
 * découvrir la boutique vide pendant une demi-seconde annulerait le bénéfice.
 */

/** Avant cela, l'écran n'a pas commencé à apparaître (voir l'animation en CSS). */
const SEUIL_VISIBLE = 400;

/**
 * Une fois montré, il reste au moins ce temps-là.
 *
 * Sans ce plancher, une connexion juste au-dessus du seuil verrait l'écran
 * monter à mi-opacité puis repartir aussitôt : un battement de paupière, qui
 * donne l'impression d'un défaut d'affichage plutôt que d'une intention.
 */
const DUREE_MINIMALE = 600;

const DUREE_FONDU = 420;

let fait = false;

export function retirerChargement(): void {
  if (fait) return;
  fait = true;

  const ecran = document.getElementById('chargement');

  if (ecran === null) return;

  // Millisecondes écoulées depuis le début de la navigation, mesurées par le
  // navigateur lui-même : aucun horodatage à poser dans le HTML, donc rien
  // qu'une politique de sécurité du contenu puisse refuser.
  const depuis = performance.now();

  // Sous le seuil, l'écran n'a jamais commencé à apparaître : le faire sortir
  // en fondu le rendrait visible au moment précis où l'on veut qu'il ne le
  // soit pas. On le retire sans cérémonie.
  if (depuis < SEUIL_VISIBLE) {
    ecran.remove();

    return;
  }

  const reste = Math.max(0, SEUIL_VISIBLE + DUREE_MINIMALE - depuis);

  window.setTimeout(() => {
    ecran.setAttribute('data-part', '');
    window.setTimeout(() => ecran.remove(), DUREE_FONDU);
  }, reste);
}
