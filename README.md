# Gymify Web

## Système de Gestion de Salle de Sport

Gymify Web est une application web développée dans le cadre des cours de l’École d’Ingénieurs ESPRIT pour l’année universitaire 2024-2025.

L’application permet de gérer plusieurs salles de sport à travers une plateforme centralisée destinée aux administrateurs, responsables de salles, entraîneurs et sportifs. Elle couvre plusieurs modules : gestion des utilisateurs, salles, activités, abonnements, événements, cours, paiements, avis, blogs, produits et réclamations.

---

## Table des matières

* [Aperçu du projet](#aperçu-du-projet)
* [Fonctionnalités](#fonctionnalités)
* [Rôles utilisateurs](#rôles-utilisateurs)
* [Pile technologique](#pile-technologique)
* [Structure du projet](#structure-du-projet)
* [Prérequis](#prérequis)
* [Installation](#installation)
* [Configuration](#configuration)
* [Lancement de l’application](#lancement-de-lapplication)
* [Utilisation](#utilisation)
* [Présentation](#présentation)
* [Topics](#topics)
* [Remerciements](#remerciements)

---

## Aperçu du projet

Gymify Web vise à simplifier la gestion quotidienne des salles de sport en proposant une interface moderne, intuitive et réactive.

L’application permet notamment de :

* gérer plusieurs salles de sport depuis une seule plateforme ;
* administrer les utilisateurs selon leurs rôles ;
* gérer les activités, abonnements, cours et événements ;
* organiser les plannings des entraîneurs ;
* permettre aux sportifs de consulter les offres, participer aux événements et effectuer des paiements ;
* gérer les produits, avis, blogs et réclamations ;
* intégrer des services externes comme Stripe, Google Auth, Google Calendar, reCAPTCHA, la météo et DeepInfra.

---

## Fonctionnalités

### Administration

* Gestion des utilisateurs.
* Gestion des salles de sport.
* Gestion des activités.
* Gestion des réclamations.
* Gestion des produits.
* Supervision globale des différentes succursales.

### Responsable de salle

* Ajout et gestion des abonnements.
* Gestion des événements propres à la salle.
* Gestion des équipes affectées à la salle.
* Suivi des opérations liées à une succursale spécifique.

### Entraîneur

* Création et gestion des cours.
* Organisation des plannings.
* Gestion des séances sportives.

### Sportif

* Consultation des activités.
* Consultation des abonnements.
* Consultation des événements.
* Consultation des cours.
* Consultation et achat de produits.
* Paiement en ligne.
* Participation aux événements.
* Gestion des avis.
* Contribution aux blogs.

### Fonctionnalités transversales

* Authentification sécurisée avec JWT.
* Authentification avec Google.
* Paiement en ligne avec Stripe.
* Protection avec reCAPTCHA.
* Gestion des cours avec Google Calendar.
* Affichage de la météo.
* Chatbot intégré avec DeepInfra.
* Support multi-salles.
* Interface responsive adaptée aux ordinateurs, tablettes et appareils mobiles.

---

## Rôles utilisateurs

L’application prend en charge plusieurs profils :

| Rôle                 | Description                                                                                                |
| -------------------- | ---------------------------------------------------------------------------------------------------------- |
| Administrateur       | Gère l’ensemble de la plateforme, les utilisateurs, les salles, les produits et les réclamations.          |
| Responsable de salle | Gère les abonnements, événements et équipes liés à sa salle.                                               |
| Entraîneur           | Organise les cours et les plannings.                                                                       |
| Sportif              | Consulte les offres, effectue des paiements, participe aux événements et interagit avec les blogs et avis. |

---

## Pile technologique

### Frontend

* Twig
* CSS
* JavaScript

### Backend

* Symfony
* PHP
* MySQL
* XAMPP

### Sécurité

* JWT pour l’authentification et l’autorisation.
* Google Auth pour l’authentification avec Google.
* reCAPTCHA pour renforcer la sécurité.

### Services externes

* Stripe pour les paiements en ligne.
* Google Calendar pour la gestion des cours.
* API météo pour l’affichage des conditions météorologiques.
* DeepInfra pour l’intégration du chatbot.

### Outils

* Git pour le contrôle de version.
* GitHub pour l’hébergement du dépôt.
* Composer pour la gestion des dépendances PHP.
* npm pour la gestion des dépendances frontend.
* Symfony CLI pour le lancement du serveur local.

---

## Structure du projet

```bash
gymifyweb/
├── public/
│   ├── build/
│   ├── css/
│   ├── fonts/
│   ├── img/
│   ├── js/
│   └── uploads/
│
├── src/
│   ├── Command/
│   ├── Controller/
│   ├── Doctrine/
│   ├── Entity/
│   ├── Enum/
│   ├── Form/
│   ├── Notification/
│   ├── Repository/
│   ├── Security/
│   ├── Service/
│   └── Twig/
│
├── templates/
├── .env
├── README.md
└── .gitignore
```

---

## Prérequis

Avant de lancer le projet, assurez-vous d’avoir installé :

* PHP
* Symfony CLI
* Composer
* Node.js et npm
* XAMPP
* MySQL
* Git
* Un compte Stripe pour l’intégration des paiements en ligne
* Un compte Google pour l’authentification et Google Calendar

---

## Installation

### 1. Cloner le dépôt

```bash
git clone https://github.com/Wajdbenameur1/gymifyWeb
```

### 2. Accéder au dossier du projet

```bash
cd gymifyWeb
```

### 3. Installer les dépendances PHP

```bash
composer install
```

### 4. Installer les dépendances frontend

```bash
npm install
```

---

## Configuration

### Variables d’environnement

Créer un fichier `.env.local` à partir du fichier `.env` ou `.env.example`, puis renseigner les variables nécessaires à votre environnement local.

Exemple :

```env
DATABASE_URL="mysql://root:@127.0.0.1:3306/gymifyweb?serverVersion=8.0"
STRIPE_SECRET_KEY="your_stripe_secret_key"
STRIPE_PUBLIC_KEY="your_stripe_public_key"
GOOGLE_CLIENT_ID="your_google_client_id"
GOOGLE_CLIENT_SECRET="your_google_client_secret"
RECAPTCHA_SITE_KEY="your_recaptcha_site_key"
RECAPTCHA_SECRET_KEY="your_recaptcha_secret_key"
DEEPINFRA_API_KEY="your_deepinfra_api_key"
```

### Base de données

1. Démarrer XAMPP.
2. Lancer Apache et MySQL.
3. Créer une base de données MySQL.
4. Mettre à jour la variable `DATABASE_URL` dans le fichier `.env.local`.

Créer la base de données avec Symfony :

```bash
php bin/console doctrine:database:create
```

Exécuter les migrations :

```bash
php bin/console doctrine:migrations:migrate
```

---

## Lancement de l’application

### Démarrer le serveur Symfony

```bash
symfony server:start
```

### Lancer le serveur frontend si nécessaire

```bash
npm run dev
```

Ouvrir ensuite le navigateur à l’adresse suivante :

```bash
http://localhost:8000
```

---

## Utilisation

### Administrateur

L’administrateur peut se connecter afin de gérer les utilisateurs, les salles, les activités, les réclamations et les produits.

### Responsable de salle

Le responsable de salle accède à son tableau de bord pour gérer les abonnements, les événements et les équipes liés à sa salle.

### Entraîneur

L’entraîneur peut créer, modifier et organiser les cours ainsi que les plannings.

### Sportif

Le sportif peut consulter les activités, abonnements, événements, cours et produits. Il peut également effectuer des paiements, gérer ses avis, contribuer aux blogs et participer aux événements.

---

## Présentation

Vous pouvez consulter la présentation du projet ici :

[Gymify web desktop presentation](Gymify-web-desktop-presentation.pdf)

---

## Topics

* Développement web
* Symfony
* PHP
* Twig
* CSS
* JavaScript
* MySQL
* XAMPP
* Stripe
* JWT
* Google Auth
* Google Calendar
* reCAPTCHA
* DeepInfra
* API météo
* Intelligence artificielle
* Machine Learning
* Paiement en ligne

---

## Remerciements

Ce projet a été réalisé sous la direction du corps professoral de l’École d’Ingénieurs ESPRIT.

Nous remercions particulièrement Madame Sassi Soumaya et Madame Maroua Douiri pour leur accompagnement, leur soutien et leurs retours tout au long du développement du projet.
