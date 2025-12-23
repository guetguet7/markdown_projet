# Installation et mise en route

## Prérequis
- PHP >= 8.4 avec les extensions `ctype`, `iconv` et le driver PDO de votre base (ex. `pdo_pgsql` ou `pdo_mysql`)
- Composer
- Docker Compose
- Symfony CLI (ou à défaut `php -S`)
- Git

## Installation pas à pas
1) Cloner le projet  
```bash
git clone <url-du-repo> && cd Projet_markdown/markdown
```

2) Copier l'environnement et définir le secret  
```bash
cp .env .env.local
APP_SECRET=$(php -r "echo bin2hex(random_bytes(16));")
```
Insérez la valeur de `APP_SECRET` dans `.env.local`.

3) Choisir et configurer la base de données  
- Recommandé (Docker/PostgreSQL) : dans `.env.local`, définissez  
  `DATABASE_URL="mysql://admin:*studi@aksis*@127.0.0.1:3306/markdown?serverVersion=8.0.43&charset=utf8mb4"`
  ps: il n'y a rien dans la base de données mais j'en ai créée une.
`  
- Alternative MySQL locale : adaptez le `DATABASE_URL` existant à votre serveur MySQL.

4) Démarrer les services Docker (base + Mailpit)  
```bash
docker compose up -d database mailer
ps: je n'utilise pas de mailer 
```

5) Installer les dépendances PHP  
```bash
composer install
```

6) Initialiser la base et appliquer les migrations  
```bash
php bin/console doctrine:database:create
php bin/console doctrine:migrations:migrate -n
```

7) (Optionnel) Compiler les assets  
```bash
php bin/console asset-map:compile
```

8) Lancer le serveur applicatif  
```bash
symfony server:start -d
# ou
php -S 127.0.0.1:8000 -t public
```

9) Accéder à l'application  
Ouvrez http://127.0.0.1:8000/ dans le navigateur. 

## Notes
- Si vous utilisez MySQL ou PostgreSQL installés localement hors Docker, assurez-vous que l'utilisateur, le mot de passe et la base existent puis ajustez `DATABASE_URL`.
- Les conteneurs et les mots de passe fournis sont uniquement pour le développement local. Remplacez-les pour un déploiement réel.
