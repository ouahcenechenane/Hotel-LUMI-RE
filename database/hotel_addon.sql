-- ============================================================
--  HÔTEL LUXE — Addon : Hotel Management System
--  À importer APRÈS hotel.sql
--  Ajoute : piscines, piscine_reservations, notifications,
--           services, clients (view), et données de test
-- ============================================================

USE `hotel_luxe`;

-- ─── PISCINES ─────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `piscines` (
  `id`          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `nom`         VARCHAR(100)  NOT NULL,
  `description` TEXT,
  `capacite`    SMALLINT      NOT NULL DEFAULT 20,
  `tarif_heure` DECIMAL(10,2) NOT NULL DEFAULT 50.00,
  `statut`      ENUM('disponible','maintenance','fermee') DEFAULT 'disponible',
  `created_at`  TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

INSERT IGNORE INTO `piscines` (`nom`,`description`,`capacite`,`tarif_heure`) VALUES
('Piscine Extérieure','Grande piscine à débordement avec vue sur les jardins.',40,80.00),
('Piscine Intérieure','Piscine chauffée ouverte toute l\'année.',25,60.00),
('Piscine Privée Suite Royale','Piscine exclusive réservée aux clients de la Suite Royale.',6,120.00);

-- ─── RÉSERVATIONS PISCINES ───────────────────────────────────
CREATE TABLE IF NOT EXISTS `piscine_reservations` (
  `id`           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `reference`    VARCHAR(20)   NOT NULL UNIQUE,
  `piscine_id`   INT UNSIGNED,
  `nom_client`   VARCHAR(200)  NOT NULL,
  `email_client` VARCHAR(191)  NOT NULL,
  `telephone`    VARCHAR(20),
  `date_res`     DATE          NOT NULL,
  `heure_debut`  TIME          NOT NULL,
  `heure_fin`    TIME          NOT NULL,
  `nb_personnes` TINYINT       NOT NULL DEFAULT 2,
  `tarif_total`  DECIMAL(10,2) DEFAULT 0,
  `statut`       ENUM('en_attente','confirmee','annulee') DEFAULT 'en_attente',
  `message`      TEXT,
  `created_at`   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`piscine_id`) REFERENCES `piscines`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ─── NOTIFICATIONS ───────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `notifications` (
  `id`         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `type`       ENUM('reservation_chambre','reservation_salle','reservation_piscine','reservation_restaurant','autre') NOT NULL,
  `titre`      VARCHAR(255)  NOT NULL,
  `message`    TEXT          NOT NULL,
  `ref_id`     INT UNSIGNED,
  `lue`        TINYINT(1)    DEFAULT 0,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ─── SERVICES ────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `services` (
  `id`          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `categorie`   ENUM('chambre','salle','piscine','restaurant','spa','autre') NOT NULL,
  `nom`         VARCHAR(100)  NOT NULL,
  `description` TEXT,
  `capacite`    SMALLINT,
  `tarif`       DECIMAL(10,2),
  `unite`       VARCHAR(30)   DEFAULT 'unité',
  `statut`      ENUM('actif','inactif') DEFAULT 'actif',
  `created_at`  TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

INSERT IGNORE INTO `services` (`categorie`,`nom`,`description`,`capacite`,`tarif`,`unite`) VALUES
('chambre','Chambre Simple','Élégante chambre individuelle avec vue sur les jardins.',1,190.00,'nuit'),
('chambre','Chambre Double','Spacieuse chambre double avec lit king-size et terrasse.',2,290.00,'nuit'),
('chambre','Suite Luxe','Suite panoramique avec salon séparé et service de majordome.',4,590.00,'nuit'),
('salle','Grande Salle des Fêtes','Salle de 500 m² pouvant accueillir jusqu\'à 300 convives.',300,2500.00,'journée'),
('salle','Salle Jardin','Salle semi-ouverte avec vue sur les jardins, idéale pour 80 personnes.',80,1200.00,'journée'),
('piscine','Piscine Extérieure','Grande piscine à débordement avec vue sur les jardins.',40,80.00,'heure'),
('piscine','Piscine Intérieure','Piscine chauffée ouverte toute l\'année.',25,60.00,'heure'),
('restaurant','Menu Découverte','Menu 5 services signé par notre chef étoilé.',NULL,95.00,'personne'),
('restaurant','Menu Prestige','Menu 7 services avec accord mets et vins.',NULL,145.00,'personne'),
('spa','Soin Signature','Soin corps complet 90 minutes.',1,180.00,'séance');

-- ─── DONNÉES DE TEST ─────────────────────────────────────────

-- Quelques réservations de piscines
INSERT IGNORE INTO `piscine_reservations`
  (`reference`,`piscine_id`,`nom_client`,`email_client`,`telephone`,`date_res`,`heure_debut`,`heure_fin`,`nb_personnes`,`tarif_total`,`statut`) VALUES
('PISC-20240615-001', 1,'Martin Léa','lea.martin@email.fr','0612345678','2025-06-15','10:00','12:00',4,160.00,'confirmee'),
('PISC-20240620-002', 2,'Dupont Thomas','thomas.dupont@email.fr','0698765432','2025-06-20','14:00','16:00',2,120.00,'en_attente'),
('PISC-20240701-003', 1,'Bernard Claire','claire.bernard@email.fr','0654321987','2025-07-01','09:00','11:00',6,160.00,'confirmee'),
('PISC-20240710-004', 2,'Petit Antoine','antoine.petit@email.fr','0623456789','2025-07-10','15:00','17:00',3,120.00,'en_attente');

-- Quelques notifications de test
INSERT IGNORE INTO `notifications` (`type`,`titre`,`message`,`lue`) VALUES
('reservation_chambre','Nouvelle réservation chambre','M. Dubois a réservé la Suite Luxe pour 3 nuits.',0),
('reservation_restaurant','Nouvelle réservation restaurant','Mme Laurent souhaite dîner pour 4 personnes ce soir.',0),
('reservation_piscine','Nouvelle réservation piscine','M. Martin a réservé la piscine extérieure de 10h à 12h.',0),
('reservation_salle','Nouvelle réservation salle','Mme Petit organise un mariage pour 150 personnes.',0);

-- Quelques réservations de test supplémentaires (chambres)
INSERT IGNORE INTO `reservations`
  (`reference`,`room_id`,`nom_client`,`email_client`,`telephone`,`date_arrivee`,`date_depart`,`nb_personnes`,`prix_total`,`statut`) VALUES
('CH-20250101-TEST1',1,'Dubois Jean','jean.dubois@email.fr','0611223344','2025-07-01','2025-07-04',1,570.00,'confirmee'),
('CH-20250102-TEST2',4,'Moreau Sophie','sophie.moreau@email.fr','0622334455','2025-07-05','2025-07-08',2,870.00,'confirmee'),
('CH-20250103-TEST3',7,'Leclerc Paul','paul.leclerc@email.fr','0633445566','2025-07-10','2025-07-12',2,780.00,'en_attente'),
('CH-20250104-TEST4',10,'Garnier Marie','marie.garnier@email.fr','0644556677','2025-08-01','2025-08-05',3,2360.00,'confirmee'),
('CH-20250105-TEST5',11,'Lambert Pierre','pierre.lambert@email.fr','0655667788','2025-08-10','2025-08-17',4,8400.00,'confirmee');

-- Quelques réservations restaurant de test
INSERT IGNORE INTO `restaurant_reservations`
  (`reference`,`nom_client`,`email_client`,`telephone`,`date_res`,`heure_res`,`nb_couverts`,`menu`,`statut`) VALUES
('REST-20250601-T1','Rousseau Alice','alice.rousseau@email.fr','0666778899','2025-07-01','20:00',2,'prestige','confirmee'),
('REST-20250602-T2','Simon Marc','marc.simon@email.fr','0677889900','2025-07-03','19:30',4,'decouverte','en_attente'),
('REST-20250603-T3','Michel Isabelle','isabelle.michel@email.fr','0688990011','2025-07-05','21:00',6,'carte','confirmee');

-- Quelques réservations d'événements de test
INSERT IGNORE INTO `event_reservations`
  (`reference`,`nom_client`,`email_client`,`telephone`,`type_event`,`date_event`,`nb_invites`,`salle`,`budget`,`statut`) VALUES
('EVT-20250601-T1','Fontaine Sarah','sarah.fontaine@email.fr','0699001122','Mariage','2025-08-15',150,'Grande Salle',15000.00,'confirmee'),
('EVT-20250602-T2','Lefebvre Hugo','hugo.lefebvre@email.fr','0600112233','Anniversaire','2025-07-20',80,'Salle Jardin',5000.00,'en_attente'),
('EVT-20250603-T3','Chevalier Nadia','nadia.chevalier@email.fr','0611223340','Séminaire','2025-09-10',40,'Salle Jardin',3000.00,'en_attente');
