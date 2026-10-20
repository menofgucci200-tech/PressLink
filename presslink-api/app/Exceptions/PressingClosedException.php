<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Action d'accueil des clients (commande, paiement, remise, annulation,
 * nouveau client) refusée : le pressing est fermé d'après ses horaires.
 */
class PressingClosedException extends RuntimeException {}
