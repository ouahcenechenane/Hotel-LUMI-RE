-- ============================================================
--  HÔTEL LUXE — Base de données complète
--  Importer dans phpMyAdmin : http://localhost/phpmyadmin
-- ============================================================

SET FOREIGN_KEY_CHECKS = 0;
SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";

CREATE DATABASE IF NOT EXISTS `hotel_luxe`
  DEFAULT CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE `hotel_luxe`;

-- ─── TYPES DE CHAMBRES ───────────────────────────────────────
CREATE TABLE `room_types` (
  `id`           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `slug`         VARCHAR(50)     NOT NULL UNIQUE,
  `nom`          VARCHAR(100)    NOT NULL,
  `description`  TEXT,
  `capacite_max` TINYINT         NOT NULL DEFAULT 2,
  `prix_nuit`    DECIMAL(10,2)   NOT NULL,
  `image`        VARCHAR(255),
  `created_at`   TIMESTAMP       DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

INSERT INTO `room_types` (`slug`,`nom`,`description`,`capacite_max`,`prix_nuit`) VALUES
('simple',      'Chambre Simple',  'Élégante chambre individuelle avec vue sur les jardins.',         1,  190.00),
('double',      'Chambre Double',  'Spacieuse chambre double avec lit king-size et terrasse.',        2,  290.00),
('couple',      'Chambre Couple',  'Romantique chambre avec jacuzzi privatif et champagne offert.',   2,  390.00),
('suite-luxe',  'Suite Luxe',      'Suite panoramique avec salon séparé et service de majordome.',    4,  590.00),
('suite-royale','Suite Royale',    'Notre suite signature : piscine privée, terrasse 180°, chef.',    6, 1200.00);

-- ─── CHAMBRES ────────────────────────────────────────────────
CREATE TABLE `rooms` (
  `id`           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `numero`       VARCHAR(10)     NOT NULL UNIQUE,
  `etage`        TINYINT         NOT NULL,
  `room_type_id` INT UNSIGNED    NOT NULL,
  `statut`       ENUM('disponible','occupee','maintenance') DEFAULT 'disponible',
  `description`  TEXT,
  `created_at`   TIMESTAMP       DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`room_type_id`) REFERENCES `room_types`(`id`)
) ENGINE=InnoDB;

INSERT INTO `rooms` (`numero`,`etage`,`room_type_id`,`statut`) VALUES
('101',1,1,'disponible'),('102',1,1,'disponible'),('103',1,1,'disponible'),
('201',2,2,'disponible'),('202',2,2,'disponible'),('203',2,2,'occupee'),
('301',3,3,'disponible'),('302',3,3,'disponible'),
('401',4,4,'disponible'),('402',4,4,'disponible'),
('501',5,5,'disponible'),('502',5,5,'disponible');

-- ─── UTILISATEURS ────────────────────────────────────────────
CREATE TABLE `users` (
  `id`           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `nom`          VARCHAR(100)    NOT NULL,
  `prenom`       VARCHAR(100)    NOT NULL,
  `email`        VARCHAR(191)    NOT NULL UNIQUE,
  `telephone`    VARCHAR(20),
  `password`     VARCHAR(255)    NOT NULL,
  `token_reset`  VARCHAR(100),
  `created_at`   TIMESTAMP       DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ─── ADMINS ──────────────────────────────────────────────────
CREATE TABLE `admins` (
  `id`         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `nom`        VARCHAR(100)  NOT NULL,
  `email`      VARCHAR(191)  NOT NULL UNIQUE,
  `password`   VARCHAR(255)  NOT NULL,
  `role`       ENUM('superadmin','manager','receptionniste') DEFAULT 'receptionniste',
  `created_at` TIMESTAMP     DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Mot de passe par défaut : Admin@1234 (à changer impérativement)
INSERT INTO `admins` (`nom`,`email`,`password`,`role`) VALUES
('Super Admin','admin@hotel-luxe.com', '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'superadmin');

-- ─── RÉSERVATIONS CHAMBRES ───────────────────────────────────
CREATE TABLE `reservations` (
  `id`              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `reference`       VARCHAR(20)       NOT NULL UNIQUE,
  `user_id`         INT UNSIGNED,
  `room_id`         INT UNSIGNED      NOT NULL,
  `nom_client`      VARCHAR(200)      NOT NULL,
  `email_client`    VARCHAR(191)      NOT NULL,
  `telephone`       VARCHAR(20),
  `date_arrivee`    DATE              NOT NULL,
  `date_depart`     DATE              NOT NULL,
  `nb_personnes`    TINYINT           NOT NULL DEFAULT 1,
  `prix_total`      DECIMAL(10,2)     NOT NULL,
  `statut`          ENUM('en_attente','confirmee','annulee','terminee') DEFAULT 'en_attente',
  `demandes_spec`   TEXT,
  `created_at`      TIMESTAMP         DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`room_id`)   REFERENCES `rooms`(`id`),
  FOREIGN KEY (`user_id`)   REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ─── RÉSERVATIONS RESTAURANT ─────────────────────────────────
CREATE TABLE `restaurant_reservations` (
  `id`           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `reference`    VARCHAR(20)   NOT NULL UNIQUE,
  `nom_client`   VARCHAR(200)  NOT NULL,
  `email_client` VARCHAR(191)  NOT NULL,
  `telephone`    VARCHAR(20),
  `date_res`     DATE          NOT NULL,
  `heure_res`    TIME          NOT NULL,
  `nb_couverts`  TINYINT       NOT NULL DEFAULT 2,
  `menu`         ENUM('decouverte','prestige','carte') DEFAULT 'carte',
  `statut`       ENUM('en_attente','confirmee','annulee') DEFAULT 'en_attente',
  `message`      TEXT,
  `created_at`   TIMESTAMP     DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ─── RÉSERVATIONS ÉVÉNEMENTS ─────────────────────────────────
CREATE TABLE `event_reservations` (
  `id`           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `reference`    VARCHAR(20)   NOT NULL UNIQUE,
  `nom_client`   VARCHAR(200)  NOT NULL,
  `email_client` VARCHAR(191)  NOT NULL,
  `telephone`    VARCHAR(20),
  `type_event`   VARCHAR(100)  NOT NULL,
  `date_event`   DATE          NOT NULL,
  `nb_invites`   SMALLINT      NOT NULL,
  `salle`        VARCHAR(100),
  `budget`       DECIMAL(10,2),
  `statut`       ENUM('en_attente','confirmee','annulee') DEFAULT 'en_attente',
  `description`  TEXT,
  `created_at`   TIMESTAMP     DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ─── EMPLOYÉS ────────────────────────────────────────────────
CREATE TABLE `employees` (
  `id`           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `matricule`    VARCHAR(20)   NOT NULL UNIQUE,
  `nom`          VARCHAR(100)  NOT NULL,
  `prenom`       VARCHAR(100)  NOT NULL,
  `poste`        VARCHAR(100)  NOT NULL,
  `departement`  VARCHAR(100),
  `email`        VARCHAR(191)  UNIQUE,
  `telephone`    VARCHAR(20),
  `salaire_base` DECIMAL(10,2) NOT NULL,
  `date_embauche`DATE          NOT NULL,
  `statut`       ENUM('actif','inactif','conge') DEFAULT 'actif',
  `created_at`   TIMESTAMP     DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

INSERT INTO `employees` (`matricule`,`nom`,`prenom`,`poste`,`departement`,`salaire_base`,`date_embauche`) VALUES
('EMP001','Martin','Sophie','Directrice Générale','Direction',8500.00,'2018-01-15'),
('EMP002','Dubois','Pierre','Chef Étoilé','Restaurant',5200.00,'2019-03-01'),
('EMP003','Laurent','Marie','Chef Concierge','Réception',3100.00,'2020-06-15'),
('EMP004','Bernard','Lucas','Responsable Spa','Bien-être',3400.00,'2021-02-01');

-- ─── PRÉSENCES ───────────────────────────────────────────────
CREATE TABLE `attendance` (
  `id`           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `employee_id`  INT UNSIGNED  NOT NULL,
  `date`         DATE          NOT NULL,
  `heure_entree` TIME,
  `heure_sortie` TIME,
  `statut`       ENUM('present','absent','conge','maladie') DEFAULT 'present',
  UNIQUE KEY `emp_date` (`employee_id`,`date`),
  FOREIGN KEY (`employee_id`) REFERENCES `employees`(`id`)
) ENGINE=InnoDB;

-- ─── SALAIRES ────────────────────────────────────────────────
CREATE TABLE `salaries` (
  `id`          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `employee_id` INT UNSIGNED    NOT NULL,
  `mois`        DATE            NOT NULL,
  `salaire_base`DECIMAL(10,2)   NOT NULL,
  `primes`      DECIMAL(10,2)   DEFAULT 0,
  `deductions`  DECIMAL(10,2)   DEFAULT 0,
  `net_paye`    DECIMAL(10,2)   NOT NULL,
  `paye_le`     TIMESTAMP       DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`employee_id`) REFERENCES `employees`(`id`)
) ENGINE=InnoDB;

SET FOREIGN_KEY_CHECKS = 1;
