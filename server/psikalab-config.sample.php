<?php
/**
 * Copie de ce fichier à placer SUR LE SERVEUR, à côté de public_html (pas dedans) :
 *   domains/psikalab.space/psikalab-config.php
 *
 * Il n'est pas déployé automatiquement : créez-le une fois via le
 * gestionnaire de fichiers de hPanel. Toutes les clés sont facultatives.
 */
return [
    // Adresse qui reçoit les notifications des formulaires
    'to' => 'contact@psikalab.space',

    // Expéditeur des notifications : une adresse de votre domaine
    'from' => 'no-reply@psikalab.space',

    // Domaines autorisés à envoyer des formulaires
    'allowed_hosts' => ['psikalab.space', 'www.psikalab.space'],

    // Limite anti-abus : 5 envois max par IP toutes les 10 minutes
    'rate_limit' => 5,
    'rate_window' => 600,

    // Mettre false pour enregistrer sans envoyer d'email
    'send_mail' => true,
];
