-- ============================================================
--  MIGRATION : Système de comptes employés & permissions
--  À importer APRÈS hotel.sql et hotel_addon.sql
-- ============================================================

USE `hotel_luxe`;

-- ─── COMPTES EMPLOYÉS ─────────────────────────────────────────
-- Chaque employé (table employees) peut recevoir un compte de connexion
-- Un compte est rattaché à un service et possède des permissions JSON

CREATE TABLE IF NOT EXISTS `employee_accounts` (
  `id`          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `employee_id` INT UNSIGNED        NOT NULL,            -- FK vers employees
  `email`       VARCHAR(191)        NOT NULL UNIQUE,
  `password`    VARCHAR(255)        NOT NULL,
  `service`     ENUM('reception','restaurant','chambres','spa','piscine') NOT NULL,
  `permissions` JSON                NOT NULL,             -- liste de permissions
  `actif`       TINYINT(1)          DEFAULT 1,
  `last_login`  DATETIME            DEFAULT NULL,
  `created_by`  INT UNSIGNED        DEFAULT NULL,         -- admin_id du créateur
  `created_at`  TIMESTAMP           DEFAULT CURRENT_TIMESTAMP,
  `updated_at`  TIMESTAMP           DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`employee_id`) REFERENCES `employees`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ─── PERMISSIONS DISPONIBLES ──────────────────────────────────
-- Référence documentaire des permissions existantes
-- (utilisée pour l'interface de gestion)

CREATE TABLE IF NOT EXISTS `permissions_ref` (
  `code`        VARCHAR(50)   PRIMARY KEY,
  `libelle`     VARCHAR(100)  NOT NULL,
  `service`     VARCHAR(50)   NOT NULL,   -- service concerné
  `categorie`   VARCHAR(50)   NOT NULL    -- groupe d'affichage
) ENGINE=InnoDB;

INSERT IGNORE INTO `permissions_ref` (`code`,`libelle`,`service`,`categorie`) VALUES
-- Chambres
('voir_chambres',       'Voir les réservations chambres',       'chambres',   'Chambres'),
('approuver_chambres',  'Confirmer / Annuler chambres',         'chambres',   'Chambres'),
('creer_chambres',      'Créer une réservation chambre',        'chambres',   'Chambres'),
('gerer_chambres',      'Gérer le parc de chambres',            'chambres',   'Chambres'),
-- Restaurant
('voir_restaurant',     'Voir les réservations restaurant',     'restaurant', 'Restaurant'),
('approuver_restaurant','Confirmer / Annuler restaurant',       'restaurant', 'Restaurant'),
('creer_restaurant',    'Créer une réservation restaurant',     'restaurant', 'Restaurant'),
-- Piscine
('voir_piscine',        'Voir les réservations piscine',        'piscine',    'Piscine'),
('approuver_piscine',   'Confirmer / Annuler piscine',          'piscine',    'Piscine'),
('creer_piscine',       'Créer une réservation piscine',        'piscine',    'Piscine'),
-- Spa
('voir_spa',            'Voir les réservations spa',            'spa',        'Spa'),
('approuver_spa',       'Confirmer / Annuler spa',              'spa',        'Spa'),
('creer_spa',           'Créer une réservation spa',            'spa',        'Spa'),
-- Salles / Événements
('voir_salles',         'Voir les réservations salles/événements','reception','Salles & Événements'),
('approuver_salles',    'Confirmer / Annuler salles',           'reception',  'Salles & Événements'),
-- Clients & Stats
('voir_clients',        'Accéder à la liste des clients',       'reception',  'Divers'),
('voir_stats',          'Accéder aux statistiques',             'reception',  'Divers'),
('voir_calendrier',     'Accéder au calendrier',                'reception',  'Divers');

-- ─── LOGS D'ACTIVITÉ ──────────────────────────────────────────
-- Traçabilité des actions des employés

CREATE TABLE IF NOT EXISTS `activity_logs` (
  `id`          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `account_type`ENUM('admin','employe') NOT NULL DEFAULT 'admin',
  `account_id`  INT UNSIGNED  NOT NULL,
  `nom`         VARCHAR(200)  NOT NULL,
  `action`      VARCHAR(100)  NOT NULL,
  `details`     TEXT,
  `ip`          VARCHAR(45),
  `created_at`  TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ─── RESERVATIONS SPA ─────────────────────────────────────────
-- Table spa_reservations (si elle n'existe pas encore)

CREATE TABLE IF NOT EXISTS `spa_reservations` (
  `id`           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `reference`    VARCHAR(20)   NOT NULL UNIQUE,
  `nom_client`   VARCHAR(200)  NOT NULL,
  `email_client` VARCHAR(191)  NOT NULL,
  `telephone`    VARCHAR(20),
  `soin`         VARCHAR(100)  NOT NULL DEFAULT 'Soin Signature',
  `date_res`     DATE          NOT NULL,
  `heure_res`    TIME          NOT NULL,
  `nb_personnes` TINYINT       NOT NULL DEFAULT 1,
  `tarif_total`  DECIMAL(10,2) DEFAULT 0,
  `statut`       ENUM('en_attente','confirmee','annulee') DEFAULT 'en_attente',
  `message`      TEXT,
  `created_at`   TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Données de test spa
INSERT IGNORE INTO `spa_reservations`
  (`reference`,`nom_client`,`email_client`,`telephone`,`soin`,`date_res`,`heure_res`,`nb_personnes`,`tarif_total`,`statut`) VALUES
('SPA-2025-001','Beaumont Claire','claire.beaumont@email.fr','0612345679','Soin Signature','2025-07-02','10:00',1,180.00,'confirmee'),
('SPA-2025-002','Renard Julien','julien.renard@email.fr','0623456780','Soin Signature','2025-07-03','14:30',2,360.00,'en_attente'),
('SPA-2025-003','Collet Marie','marie.collet@email.fr','0634567891','Soin Signature','2025-07-05','11:00',1,180.00,'en_attente');

-- ─── EXEMPLE DE COMPTES EMPLOYÉS (optionnel, mot de passe: Employe@1234) ─
-- Les vrais comptes sont créés par le superadmin via l'interface

-- Exemple réception (mot de passe: Employe@1234)
INSERT IGNORE INTO `employee_accounts` (`employee_id`,`email`,`password`,`service`,`permissions`,`actif`) VALUES
(1, 'sophie.martin@hotel-luxe.com',
 '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
 'reception',
 '["voir_chambres","approuver_chambres","creer_chambres","voir_restaurant","approuver_restaurant","creer_restaurant","voir_piscine","approuver_piscine","creer_piscine","voir_spa","approuver_spa","voir_salles","approuver_salles","voir_clients","voir_calendrier"]',
 1);
