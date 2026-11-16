# Gestion-Auto

**Application web de gestion automobile développée avec Laravel.**

Gestion-Auto est une application destinée à faciliter la gestion des activités d'une structure automobile. Elle centralise les opérations liées aux véhicules, aux clients, aux achats, aux ventes, aux locations et à la gestion financière.

## Fonctionnalités principales

- **Tableau de bord :** suivi des principales activités.
- **Gestion des véhicules :** enregistrement et suivi des véhicules.
- **Gestion des motos :** enregistrement et suivi des motos.
- **Gestion des clients :** création et consultation des fiches clients.
- **Gestion des achats :** suivi des acquisitions de véhicules et articles.
- **Gestion des ventes :** suivi des ventes et des détails associés.
- **Gestion des locations :** enregistrement et suivi des locations.
- **Gestion des paiements :** suivi des règlements et modes de paiement.
- **Gestion de la caisse :** suivi des entrées et sorties d'argent.
- **Gestion des dépenses :** enregistrement et suivi des dépenses.
- **Gestion des pièces et du stock :** suivi des articles et mouvements de stock.
- **Gestion des reçus :** génération des reçus.
- **Sauvegardes :** fonctionnalités de sauvegarde des données.

## Technologies utilisées

- PHP
- Laravel
- MySQL
- HTML
- CSS
- JavaScript
- Tailwind CSS
- Vite

## Installation

### 1. Cloner le projet

```bash
git clone https://github.com/Moutala-web/Gestion-Auto.git
```

### 2. Accéder au dossier

```bash
cd Gestion-Auto
```

### 3. Installer les dépendances PHP

```bash
composer install
```

### 4. Installer les dépendances JavaScript

```bash
npm install
```

### 5. Configurer l'environnement

```bash
copy .env.example .env
```

Configurer ensuite les paramètres de connexion à la base de données dans le fichier `.env`.

### 6. Générer la clé de l'application

```bash
php artisan key:generate
```

### 7. Créer la base de données

Créer une base de données MySQL, puis exécuter :

```bash
php artisan migrate --seed
```

### 8. Compiler les ressources

```bash
npm run build
```

### 9. Démarrer l'application

```bash
php artisan serve
```

Ouvrir ensuite : http://127.0.0.1:8000

## Auteur

**Sawadogo Moutala**

Développeur d'applications web – Laravel / PHP

## Licence

Projet publié à des fins de présentation et de démonstration.
