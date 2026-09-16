/**
 * Legal page content. Everything in [crochets] is a placeholder only the owner can fill
 * (company registration, tax ID, host, APDP receipt number) — never invent those values.
 * The Benin framework is loi n° 2017-20 du 20 avril 2018 portant Code du numérique.
 */

export type LegalSection = {
  heading: string;
  paragraphs?: string[];
  list?: string[];
};

export type LegalPage = {
  slug: string;
  title: string;
  navLabel: string;
  lead: string;
  updated: string;
  sections: LegalSection[];
};

const UPDATED = '15 septembre 2026';

export const LEGAL_PAGES: Record<string, LegalPage> = {
  'mentions-legales': {
    slug: 'mentions-legales',
    title: 'Mentions légales',
    navLabel: 'Mentions légales',
    lead: "Informations relatives à l'éditeur du site rccsneakers.bj et à son hébergement.",
    updated: UPDATED,
    sections: [
      {
        heading: 'Éditeur du site',
        list: [
          'Dénomination sociale : [RAISON SOCIALE]',
          'Forme juridique : [SARL / SA / Entreprise individuelle]',
          'Capital social : [MONTANT] F CFA',
          'Siège social : [ADRESSE COMPLÈTE], Cotonou, Bénin',
          'RCCM : [NUMÉRO RCCM]',
          'IFU : [NUMÉRO IFU]',
          'Téléphone : [NUMÉRO]',
          'E-mail : contact@rccsneakers.bj',
        ],
      },
      {
        heading: 'Directeur de la publication',
        paragraphs: ['[NOM ET PRÉNOM], en qualité de [FONCTION].'],
      },
      {
        heading: 'Hébergement',
        list: [
          'Hébergeur : [NOM DE L’HÉBERGEUR]',
          'Adresse : [ADRESSE DE L’HÉBERGEUR]',
          'Contact : [TÉLÉPHONE OU E-MAIL]',
        ],
      },
      {
        heading: 'Protection des données personnelles',
        paragraphs: [
          "Les traitements de données à caractère personnel réalisés sur ce site ont fait l'objet d'une déclaration auprès de l'Autorité de Protection des Données à caractère Personnel (APDP) sous le numéro [NUMÉRO DE RÉCÉPISSÉ APDP], conformément à la loi n° 2017-20 du 20 avril 2018 portant Code du numérique en République du Bénin.",
          'Les modalités de collecte et de traitement sont détaillées dans notre politique de confidentialité.',
        ],
      },
      {
        heading: 'Propriété intellectuelle',
        paragraphs: [
          "La structure du site, sa charte graphique, ses textes et ses visuels propres sont la propriété exclusive de l'éditeur. Toute reproduction, représentation ou adaptation, totale ou partielle, sans autorisation écrite préalable, est interdite.",
        ],
      },
      {
        heading: 'Marques citées',
        paragraphs: [
          "RCC Sneakers est un revendeur indépendant. Les marques, noms de modèles et logos cités sur ce site (Nike, Jordan, adidas, New Balance, ASICS, Puma, Converse, Reebok, Vans, HOKA, Salomon, On, Birkenstock et autres) appartiennent à leurs titulaires respectifs.",
          "Leur mention sert exclusivement à identifier les produits proposés à la vente. RCC Sneakers n'est ni affilié, ni partenaire, ni agréé par ces marques, et ne prétend à aucune forme de représentation officielle.",
        ],
      },
      {
        heading: 'Responsabilité',
        paragraphs: [
          "L'éditeur s'efforce d'assurer l'exactitude des informations publiées et la disponibilité du site, sans pouvoir la garantir de façon absolue. Les photographies et rendus des produits sont les plus fidèles possibles mais ne sauraient engager l'éditeur sur des différences mineures d'aspect ou de teinte.",
        ],
      },
      {
        heading: 'Liens externes',
        paragraphs: [
          "Le site peut renvoyer vers des sites tiers dont l'éditeur ne maîtrise ni le contenu ni les pratiques. Leur consultation relève de la seule responsabilité de l'utilisateur.",
        ],
      },
    ],
  },

  cgv: {
    slug: 'cgv',
    title: 'Conditions générales de vente',
    navLabel: 'CGV',
    lead: "Les règles qui encadrent toute commande passée sur rccsneakers.bj. Elles s'appliquent dès la validation de la commande.",
    updated: UPDATED,
    sections: [
      {
        heading: '1. Objet et champ d’application',
        paragraphs: [
          "Les présentes conditions générales régissent les ventes de chaussures, de maillots et d'accessoires conclues entre RCC Sneakers et tout client agissant en qualité de consommateur.",
          "Elles sont soumises au Livre IV de la loi n° 2017-20 du 20 avril 2018 portant Code du numérique en République du Bénin, relatif au commerce électronique.",
          "Toute commande implique l'acceptation pleine et entière des présentes conditions, acceptées par le client lors de la validation du panier.",
        ],
      },
      {
        heading: '2. Produits',
        paragraphs: [
          "Chaque produit est présenté avec sa marque, son modèle, son coloris, ses tailles disponibles et, lorsqu'elle est connue, sa référence fabricant.",
          "Les offres sont valables dans la limite des stocks disponibles. En cas d'indisponibilité constatée après la commande, le client en est informé sans délai et remboursé intégralement.",
        ],
      },
      {
        heading: '3. Prix',
        paragraphs: [
          "Les prix sont affichés en francs CFA (XOF), toutes taxes comprises le cas échéant. Les frais de livraison sont indiqués séparément avant la validation définitive de la commande.",
          'RCC Sneakers se réserve le droit de modifier ses prix à tout moment. Le prix applicable est celui affiché au moment de la validation de la commande.',
        ],
      },
      {
        heading: '4. Commande',
        paragraphs: [
          'La commande se déroule en quatre étapes : sélection du produit et de la taille, ajout au panier, saisie des coordonnées de livraison, puis validation et paiement.',
          "Avant validation, le client dispose d'un récapitulatif lui permettant de vérifier et de corriger sa commande. La validation vaut engagement.",
          'Un accusé de réception est envoyé par e-mail ou par message dès la confirmation de la commande.',
        ],
      },
      {
        heading: '5. Paiement',
        paragraphs: ['Les moyens de paiement acceptés sont les suivants :'],
        list: [
          'Mobile money : MTN MoMo et Moov Money',
          'Espèces à la livraison, dans les zones où ce mode est proposé',
          'Virement bancaire sur demande, pour les commandes importantes',
        ],
      },
      {
        heading: '6. Livraison',
        paragraphs: [
          'Les délais indicatifs sont de 24 heures à Cotonou et de 72 heures pour le reste du territoire béninois, à compter de la confirmation du paiement.',
          "Le client est tenu de vérifier l'état du colis à la réception et de signaler toute anomalie immédiatement au livreur.",
          'Les modalités complètes figurent dans notre page Livraison et retours.',
        ],
      },
      {
        heading: '7. Droit de rétractation',
        paragraphs: [
          "Conformément au Code du numérique, le client dispose d'un droit de rétractation qu'il peut exercer sans avoir à motiver sa décision, dans le délai légal en vigueur courant à compter de la réception du produit.",
          "Le produit doit être retourné dans son état d'origine, non porté, complet et dans son emballage, accompagné de la preuve d'achat.",
          "Les frais de livraison sont remboursés au client lorsque la rétractation résulte d'un dépassement du délai de livraison ou du non-respect de ses obligations par le vendeur.",
        ],
      },
      {
        heading: '8. Garanties',
        paragraphs: [
          "RCC Sneakers garantit les produits vendus contre les vices cachés qui les rendent impropres à l'usage auquel ils sont destinés, ou qui diminuent cet usage au point que le client ne les aurait pas acquis s'il en avait eu connaissance.",
          "Cette garantie ne couvre ni l'usure normale, ni les dommages résultant d'un usage inadapté ou d'un défaut d'entretien.",
        ],
      },
      {
        heading: '9. Réclamations et litiges',
        paragraphs: [
          'Toute réclamation doit être adressée au service client par e-mail ou par téléphone. RCC Sneakers s’engage à y répondre dans les meilleurs délais et à rechercher une solution amiable.',
          'À défaut de règlement amiable, le litige relève des juridictions compétentes de Cotonou.',
        ],
      },
      {
        heading: '10. Droit applicable',
        paragraphs: ['Les présentes conditions sont soumises au droit béninois.'],
      },
    ],
  },

  confidentialite: {
    slug: 'confidentialite',
    title: 'Politique de confidentialité',
    navLabel: 'Confidentialité',
    lead: 'Quelles données nous collectons, pourquoi, combien de temps nous les gardons, et comment vous gardez la main dessus.',
    updated: UPDATED,
    sections: [
      {
        heading: 'Responsable du traitement',
        paragraphs: [
          "Le responsable du traitement est [RAISON SOCIALE], dont le siège est situé [ADRESSE], à Cotonou. Contact : contact@rccsneakers.bj.",
          "Les traitements sont déclarés auprès de l'APDP sous le numéro [NUMÉRO DE RÉCÉPISSÉ APDP].",
        ],
      },
      {
        heading: 'Données collectées',
        paragraphs: ['Nous ne collectons que ce qui est nécessaire au fonctionnement de la boutique :'],
        list: [
          'Identité : nom et prénom',
          'Coordonnées : e-mail, numéro de téléphone, adresse de livraison',
          'Compte : identifiant et mot de passe chiffré',
          'Commandes : historique, tailles, montants, moyen de paiement utilisé',
          'Navigation : pages consultées et données techniques anonymisées',
        ],
      },
      {
        heading: 'Finalités',
        list: [
          'Traiter et livrer les commandes',
          'Gérer les comptes clients et le service après-vente',
          'Répondre aux demandes envoyées via le formulaire de contact',
          'Envoyer la lettre d’information, uniquement après inscription volontaire',
          'Améliorer le site et mesurer son audience',
        ],
      },
      {
        heading: 'Base du traitement',
        paragraphs: [
          "Les traitements reposent selon les cas sur l'exécution du contrat de vente, sur le respect d'une obligation légale, ou sur votre consentement — notamment pour la lettre d'information, révocable à tout moment.",
        ],
      },
      {
        heading: 'Destinataires',
        paragraphs: [
          "Vos données sont destinées aux équipes internes de RCC Sneakers et à nos prestataires strictement nécessaires : livreurs, opérateurs de paiement mobile, hébergeur. Aucune donnée n'est vendue ni cédée à des tiers à des fins commerciales.",
        ],
      },
      {
        heading: 'Durée de conservation',
        list: [
          'Données de commande : durée légale de conservation comptable',
          'Compte client : jusqu’à sa suppression par le client, puis suppression ou anonymisation',
          'Lettre d’information : jusqu’au désabonnement',
          'Données de navigation : durée limitée définie dans la politique de cookies',
        ],
      },
      {
        heading: 'Sécurité',
        paragraphs: [
          "Les mots de passe sont stockés sous forme chiffrée. Les accès aux données sont restreints aux personnes qui en ont besoin. Les échanges avec le site sont protégés par un certificat de sécurité.",
        ],
      },
      {
        heading: 'Vos droits',
        paragraphs: [
          "Vous disposez d'un droit d'accès, de rectification, d'effacement, d'opposition et de limitation sur vos données. Vous pouvez les exercer à tout moment en écrivant à contact@rccsneakers.bj, en justifiant de votre identité.",
          "Vous pouvez également saisir l'APDP si vous estimez que vos droits ne sont pas respectés.",
        ],
      },
    ],
  },

  cookies: {
    slug: 'cookies',
    title: 'Politique de cookies',
    navLabel: 'Cookies',
    lead: 'Les traceurs déposés sur votre appareil, ce qu’ils font et comment les refuser.',
    updated: UPDATED,
    sections: [
      {
        heading: 'Qu’est-ce qu’un cookie',
        paragraphs: [
          "Un cookie est un petit fichier déposé sur votre appareil lors de la consultation d'un site. Il permet notamment de garder votre panier en mémoire d'une page à l'autre ou de mesurer la fréquentation du site.",
        ],
      },
      {
        heading: 'Cookies strictement nécessaires',
        paragraphs: [
          "Ils assurent le fonctionnement du site et ne peuvent pas être désactivés : maintien de la session, contenu du panier, préférences d'affichage, sécurité des formulaires. Ils ne requièrent pas votre consentement.",
        ],
      },
      {
        heading: 'Cookies de mesure d’audience',
        paragraphs: [
          "Ils nous indiquent quelles pages sont consultées et comment le site est utilisé, sous forme de statistiques agrégées. Ils ne sont déposés qu'après votre accord.",
        ],
      },
      {
        heading: 'Votre consentement',
        paragraphs: [
          "Lors de votre première visite, un bandeau vous permet d'accepter ou de refuser les cookies non essentiels. Votre choix est conservé et reste modifiable à tout moment depuis cette page.",
        ],
      },
      {
        heading: 'Gérer les cookies depuis votre navigateur',
        paragraphs: [
          'Vous pouvez à tout moment configurer votre navigateur pour bloquer ou supprimer les cookies. Le réglage se trouve généralement dans les paramètres de confidentialité de Chrome, Firefox, Safari ou Edge.',
          'Le blocage des cookies strictement nécessaires peut empêcher certaines fonctions du site de marcher correctement, notamment le panier.',
        ],
      },
    ],
  },

  'livraison-retours': {
    slug: 'livraison-retours',
    title: 'Livraison & retours',
    navLabel: 'Livraison & retours',
    lead: 'Délais, zones couvertes, frais, et la marche à suivre pour un échange de taille ou un retour.',
    updated: UPDATED,
    sections: [
      {
        heading: 'Zones et délais',
        list: [
          'Cotonou : livraison sous 24 heures ouvrées',
          'Reste du Bénin : expédition sous 72 heures ouvrées',
          'Hors Bénin : sur devis, nous consulter avant commande',
        ],
      },
      {
        heading: 'Frais de livraison',
        paragraphs: [
          "Les frais sont calculés selon la zone et affichés avant la validation définitive de la commande. Aucun frais n'est ajouté après paiement.",
        ],
      },
      {
        heading: 'Réception du colis',
        paragraphs: [
          "Vérifiez l'état du colis devant le livreur. Si l'emballage est ouvert ou endommagé, signalez-le immédiatement et refusez la livraison si nécessaire : une réserve formulée à la réception facilite grandement la prise en charge.",
        ],
      },
      {
        heading: 'Conditions de retour',
        paragraphs: ['Un produit est repris à condition qu’il soit :'],
        list: [
          'Non porté à l’extérieur, semelle propre et intacte',
          'Complet, avec ses étiquettes, ses lacets et sa boîte d’origine',
          'Accompagné de la preuve d’achat',
        ],
      },
      {
        heading: 'Procédure',
        paragraphs: [
          "Contactez le service client par téléphone, WhatsApp ou e-mail en indiquant votre numéro de commande et le motif. Nous vous confirmons la marche à suivre et le point de dépôt ou de collecte.",
        ],
      },
      {
        heading: 'Échange de taille',
        paragraphs: [
          "L'échange de taille est possible sous réserve de disponibilité du modèle dans la taille souhaitée. Si la taille demandée n'est plus disponible, le remboursement est proposé.",
        ],
      },
      {
        heading: 'Remboursement',
        paragraphs: [
          'Le remboursement est effectué après réception et contrôle du produit retourné, par le même moyen que celui utilisé lors du paiement, sauf accord contraire.',
        ],
      },
    ],
  },

  authenticite: {
    slug: 'authenticite',
    title: 'Garantie d’authenticité',
    navLabel: 'Authenticité',
    lead: 'Chaque paire vendue par RCC Sneakers est authentique. Voici ce que cet engagement recouvre concrètement.',
    updated: UPDATED,
    sections: [
      {
        heading: 'Notre engagement',
        paragraphs: [
          "RCC Sneakers ne vend que des produits authentiques. Aucune réplique, aucune contrefaçon, aucun article présenté comme original sans l'être n'entre dans notre stock.",
        ],
      },
      {
        heading: 'Contrôle avant mise en vente',
        paragraphs: ['Chaque paire réceptionnée passe par une vérification portant notamment sur :'],
        list: [
          'La référence fabricant et sa cohérence avec le modèle et le coloris',
          'L’étiquette intérieure : police, alignement, codes de production',
          'La qualité des matériaux, des coutures et des collages',
          'La boîte, son étiquette et les accessoires fournis',
          'La provenance et la traçabilité du lot',
        ],
      },
      {
        heading: 'À la livraison',
        paragraphs: [
          "Vous êtes libre d'examiner la paire à la réception. Si un élément vous semble douteux, signalez-le immédiatement : nous préférons une vérification de plus qu'un client qui doute.",
        ],
      },
      {
        heading: 'En cas de contestation',
        paragraphs: [
          "Si vous estimez qu'un produit vendu par RCC Sneakers n'est pas authentique, contactez-nous avec votre numéro de commande et des photographies. Après vérification, un produit reconnu non authentique est intégralement remboursé, frais de livraison compris.",
        ],
      },
      {
        heading: 'Ce que cette garantie ne couvre pas',
        paragraphs: [
          "Les variations mineures de teinte, de placement de motif ou de finition entre deux exemplaires d'un même modèle relèvent de la production de série et ne constituent pas un défaut d'authenticité.",
        ],
      },
    ],
  },
};

export const LEGAL_SLUGS = Object.keys(LEGAL_PAGES);
