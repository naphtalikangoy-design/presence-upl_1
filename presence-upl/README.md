# Présence des stagiaires - UPL

PHP 8 / PostgreSQL / CSS. Déploiement Docker sur Render.

## Déploiement (Blueprint, recommandé)
1. Poussez ce dossier sur GitHub.
2. Render > New > Blueprint > sélectionnez le dépôt. Le fichier render.yaml crée la base PostgreSQL
   (upl-presence-db) et le service web, et relie DATABASE_URL automatiquement.
3. Renseignez ADMIN_PASSWORD quand Render le demande, puis lancez le déploiement.
4. Connectez-vous avec l'identifiant admin. Les tables sont créées au premier accès.

## Déploiement manuel
1. New > PostgreSQL, copiez l'Internal Database URL.
2. New > Web Service, runtime Docker, variables : DATABASE_URL et ADMIN_PASSWORD.

## Configuration du réseau Wi-Fi
Le serveur voit l'adresse IP publique de la connexion Internet de l'UPL, pas le nom du Wi-Fi.
Connectez-vous au Wi-Fi de l'UPL avec votre appareil administrateur, ouvrez Paramètres > Réseau Wi-Fi,
cliquez sur Ajouter mon adresse actuelle, puis enregistrez. Si le fournisseur Internet change l'adresse,
mettez la liste à jour.
