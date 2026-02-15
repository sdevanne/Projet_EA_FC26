![PHP](https://img.shields.io/badge/PHP-8.3-blue)
![MongoDB](https://img.shields.io/badge/MongoDB-Database-green)
![PHPUnit](https://img.shields.io/badge/Tests-PHPUnit-success)
# ⚽ Projet FC26 – Application PHP / MongoDB

## 📌 Présentation du projet

**Projet FC26** est une application web développée en **PHP** avec une base de données **MongoDB**.

L’objectif du projet est de gérer des données issues de l’univers footballistique (inspiré de FC26) via une interface web complète permettant :

- 📖 Consulter les données
- ➕ Ajouter des éléments
- ✏️ Modifier des éléments
- ❌ Supprimer des éléments
- 🔎 Rechercher et filtrer des données

Le projet respecte les contraintes suivantes :

- Utilisation de **HTML / CSS**
- Utilisation de **PHP**
- Utilisation de **MongoDB**
- Au moins **4 collections liées entre elles**
- Implémentation de **tests unitaires**
- CRUD complet via interface
- Recherche dynamique

---

# 🗄️ Structure de la base de données

Le projet contient 4 collections principales :

## 1️⃣ Leagues

Représente les ligues (ex : Premier League, Liga, Ligue 1…)

Champs principaux :
- `code`
- `name`
- `country`
- `level`
- `createdAt`

---

## 2️⃣ Teams

Représente les clubs

Champs principaux :
- `name`
- `leagueId` (référence vers `leagues`)
- `rating`
- `att`
- `mid`
- `def`
- `budget`
- `avgAge`
- `youthDev`

---

## 3️⃣ Players

Représente les joueurs

Champs principaux :
- `playerName`
- `teamId` (référence vers `teams`)
- `leagueId`
- `positions`
- `overall`
- `age`
- `contractStart` (timestamp)
- `contractEnd` (timestamp)
- `marketValue`
- `slug`
- `createdAt`
- `updatedAt`

---

## 4️⃣ Scout Reports

Représente les rapports de scouting d’un joueur

Champs principaux :
- `playerId` (référence vers `players`)
- `rating` (note /10)
- `strengths` (array)
- `weaknesses` (array)
- `notes`
- `createdAt`

---

# 🔗 Relations entre collections

- `teams.leagueId` → référence `leagues._id`
- `players.teamId` → référence `teams._id`
- `players.leagueId` → référence `leagues._id`
- `scout_reports.playerId` → référence `players._id`

Les collections sont donc **liées entre elles**, conformément aux exigences du projet.

---

# 🚀 Installation du projet

## 1️⃣ Cloner le projet

```bash
git clone https://github.com/sdevanne/Projet_EA_FC26.git
cd Projet_EA_FC26
```

## 2️⃣ Installer les dépendances

Le projet utilise Composer.

```bash
composer install
```

Cela va installer :

- mongodb/mongodb
- phpunit (pour les tests)

## 3️⃣ Vérifier MongoDB

MongoDB doit être installé et lancé sur votre machine.

Par défaut la configuration est :

```php
"mongo_uri" => "mongodb://127.0.0.1:27017",
"db_name"   => "Projet_EA_FC26",
```

Fichier concerné :

config/config.php

## 4️⃣ Lancer le projet

Depuis la racine :

```bash
php -S localhost:8000 -t public
```

Puis ouvrir :

http://localhost:8000

---

# 🛠️ Initialisation des données (optionnel)

Des scripts sont disponibles dans /scripts :

## Reset base

```bash
php scripts/reset_db.php
```

## Seed des ligues

```bash
php scripts/seed_leagues.php
```

## Import des équipes

```bash
php scripts/import_teams.php
```

## Import des joueurs

```bash
php scripts/import_players.php
```

---

# 🧪 Tests unitaires (Obligatoire)

Le projet contient des tests unitaires PHPUnit.

Les tests couvrent :

- Fonctions utilitaires (Helpers.php)
- Conversion de dates
- Conversion monétaire
- Génération de slug
- Connexion base de test

## Lancer les tests

```bash
composer test
```

Ou :

```bash
./vendor/bin/phpunit
```

Résultat attendu :

OK (7 tests, 24 assertions)

Les tests utilisent une base séparée :

Projet_EA_FC26_test

Cela évite toute modification de la base principale.

---

# 🎯 Fonctionnalités implémentées

✔ CRUD complet pour :

- Leagues
- Teams
- Players
- Scout Reports

✔ Recherche et filtres dynamiques :

- Recherche joueurs
- Filtres OVR
- Filtres Ligue / Équipe
- Tri dynamique

✔ Relations entre collections

✔ Tests unitaires

✔ Interface claire et moderne (UI responsive)

---

# 📁 Structure du projet

config/
public/
src/
scripts/
tests/
data/
composer.json
phpunit.xml
README.md

---

# 💡 Points techniques intéressants

- Utilisation de ObjectId
- Utilisation de UTCDateTime
- Index MongoDB uniques
- Slugs dynamiques
- Pagination MongoDB
- Filtres dynamiques
- Base de test séparée
- Architecture simple MVC-like

---

# 👨‍💻 Auteur

Siméon DEVANNE, Ayoub Izague 
Bachelor Business Data Science  
UCO
