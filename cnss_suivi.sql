-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Hôte : 127.0.0.1:3306
-- Généré le : dim. 28 déc. 2025 à 17:39
-- Version du serveur : 8.2.0
-- Version de PHP : 8.2.13

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Base de données : `cnss_suivi`
--

-- --------------------------------------------------------

--
-- Structure de la table `dossiers`
--

DROP TABLE IF EXISTS `dossiers`;
CREATE TABLE IF NOT EXISTS `dossiers` (
  `id` int NOT NULL AUTO_INCREMENT,
  `numero_dossier` varchar(100) COLLATE utf8mb4_general_ci NOT NULL,
  `nom_assure` varchar(150) COLLATE utf8mb4_general_ci NOT NULL,
  `regime` enum('RP','RG') COLLATE utf8mb4_general_ci NOT NULL,
  `type_prestation_id` int NOT NULL,
  `controleur_id` int DEFAULT NULL,
  `statut` enum('recu','en_cours','soumis_chef','valide','rejete') COLLATE utf8mb4_general_ci DEFAULT 'recu',
  `date_reception` date NOT NULL,
  `date_debut_traitement` date DEFAULT NULL,
  `date_soumission_chef` date DEFAULT NULL,
  `date_validation` date DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `numero_dossier` (`numero_dossier`),
  KEY `controleur_id` (`controleur_id`),
  KEY `type_prestation_id` (`type_prestation_id`)
) ENGINE=MyISAM AUTO_INCREMENT=18 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `dossier_rejets`
--

DROP TABLE IF EXISTS `dossier_rejets`;
CREATE TABLE IF NOT EXISTS `dossier_rejets` (
  `id` int NOT NULL AUTO_INCREMENT,
  `dossier_id` int NOT NULL,
  `user_id` int NOT NULL,
  `motif` text COLLATE utf8mb4_general_ci NOT NULL,
  `date_rejet` datetime DEFAULT CURRENT_TIMESTAMP,
  `retour_effectue` tinyint(1) DEFAULT '0',
  `date_retour` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `dossier_id` (`dossier_id`),
  KEY `user_id` (`user_id`)
) ENGINE=MyISAM AUTO_INCREMENT=23 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `historique`
--

DROP TABLE IF EXISTS `historique`;
CREATE TABLE IF NOT EXISTS `historique` (
  `id` int NOT NULL AUTO_INCREMENT,
  `dossier_id` int NOT NULL,
  `user_id` int DEFAULT NULL,
  `action` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
  `commentaire` text COLLATE utf8mb4_general_ci,
  `date_action` datetime DEFAULT CURRENT_TIMESTAMP,
  `ip_address` varchar(45) COLLATE utf8mb4_general_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `dossier_id` (`dossier_id`),
  KEY `user_id` (`user_id`)
) ENGINE=MyISAM AUTO_INCREMENT=276 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `historique_statut`
--

DROP TABLE IF EXISTS `historique_statut`;
CREATE TABLE IF NOT EXISTS `historique_statut` (
  `id` int NOT NULL AUTO_INCREMENT,
  `dossier_id` int NOT NULL,
  `user_id` int NOT NULL,
  `ancien_statut` varchar(30) DEFAULT NULL,
  `nouveau_statut` varchar(30) DEFAULT NULL,
  `motif` text,
  `date_action` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `dossier_id` (`dossier_id`),
  KEY `user_id` (`user_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Structure de la table `notifications`
--

DROP TABLE IF EXISTS `notifications`;
CREATE TABLE IF NOT EXISTS `notifications` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `dossier_id` int DEFAULT NULL,
  `message` varchar(255) DEFAULT NULL,
  `lu` tinyint(1) DEFAULT '0',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `dossier_id` (`dossier_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Structure de la table `pieces_fournies`
--

DROP TABLE IF EXISTS `pieces_fournies`;
CREATE TABLE IF NOT EXISTS `pieces_fournies` (
  `id` int NOT NULL AUTO_INCREMENT,
  `dossier_id` int NOT NULL,
  `piece_requise_id` int NOT NULL,
  `original_fourni` tinyint(1) DEFAULT '0',
  `copie_fourni` tinyint(1) DEFAULT '0',
  `valide` tinyint(1) DEFAULT '0',
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_piece` (`dossier_id`,`piece_requise_id`)
) ENGINE=MyISAM AUTO_INCREMENT=99 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `pieces_requises`
--

DROP TABLE IF EXISTS `pieces_requises`;
CREATE TABLE IF NOT EXISTS `pieces_requises` (
  `id` int NOT NULL AUTO_INCREMENT,
  `type_prestation_id` int NOT NULL,
  `nom_piece` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
  `ordre` int DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `type_prestation_id` (`type_prestation_id`)
) ENGINE=MyISAM AUTO_INCREMENT=27 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `pieces_requises`
--

INSERT INTO `pieces_requises` (`id`, `type_prestation_id`, `nom_piece`, `ordre`) VALUES
(1, 1, 'Attestation de travail (en cas de décès d\'un travailleur en exercice)', 1),
(2, 1, 'Acte de décès', 2),
(3, 1, 'Certificat de propriété', 3),
(4, 1, 'Acte(s) de mariage(s)', 4),
(5, 1, 'Certificat(s) de non-remariage et de non-divorce', 5),
(6, 1, 'Acte(s) de naissance(s) des enfants de moins de 21 ans', 6),
(7, 1, 'Certificat de scolarité pour les enfants âgés de plus de 21 ans', 7),
(8, 1, 'Carte d\'immatriculation à la CNSS (à restituer)', 8),
(9, 1, 'Livret de pension (si le défunt est déjà retraité, à restituer)', 9),
(10, 1, 'Copie livret de pension veuve/veuf (en cas de perception d\'une pension)', 10),
(11, 1, 'Attestation sur l\'honneur (en cas de défaut d\'attestation de travail)', 11),
(12, 1, 'RIB', 12),
(13, 1, 'Carte d\'identité nationale des conjoint(s) sur présentation d\'originale', 13),
(14, 2, 'Certificat(s) de travail', 1),
(15, 2, 'Carte d\'immatriculation à la CNSS (à restituer)', 2),
(16, 2, 'Acte(s) de mariage(s)', 3),
(17, 2, 'Fiche familiale d\'état civil', 4),
(18, 2, 'Certificat médical d\'inaptitude (pour toute demande de retraite anticipée)', 5),
(19, 2, 'Acte(s) de naissance(s) des enfants âgés de moins de 21 ans', 6),
(20, 2, 'Carte d\'identité (CIN) du travailleur', 7),
(21, 2, 'Carte d\'identité (CIN) du ou des conjoint(s)', 8),
(22, 2, 'RIB', 9),
(23, 3, 'Certificat(s) de travail', 1),
(24, 3, 'Carte d\'immatriculation à la CNSS (à restituer)', 2),
(25, 3, 'Décompte du salaire en cas d\'un employé du Budget National', 3),
(26, 3, 'Carte d\'identité (CIN) du travailleur', 4);

-- --------------------------------------------------------

--
-- Structure de la table `types_prestation`
--

DROP TABLE IF EXISTS `types_prestation`;
CREATE TABLE IF NOT EXISTS `types_prestation` (
  `id` int NOT NULL AUTO_INCREMENT,
  `nom` varchar(150) COLLATE utf8mb4_general_ci NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `types_prestation`
--

INSERT INTO `types_prestation` (`id`, `nom`) VALUES
(1, 'Dossier de réversion et d\'allocation unique'),
(2, 'Dossier de retraite (Proportionnel, Anticipé, Normal)'),
(3, 'Dossier de remboursement');

-- --------------------------------------------------------

--
-- Structure de la table `users`
--

DROP TABLE IF EXISTS `users`;
CREATE TABLE IF NOT EXISTS `users` (
  `id` int NOT NULL AUTO_INCREMENT,
  `username` varchar(50) COLLATE utf8mb4_general_ci NOT NULL,
  `password` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
  `nom_complet` varchar(100) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `role` enum('controleur','superviseur','admin') COLLATE utf8mb4_general_ci DEFAULT 'admin',
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`)
) ENGINE=MyISAM AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `users`
--

INSERT INTO `users` (`id`, `username`, `password`, `nom_complet`, `role`) VALUES
(1, 'admin', '$2y$10$q7vhvdSNFbiOKQta3UTB..XbVsBUjsH5ym8Hu0y1q6svax27NvqMa', 'Administrateur CNSS', 'admin');
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
