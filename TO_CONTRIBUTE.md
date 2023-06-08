ToDo List
=======
Comment contribuer au projet
-----------

L’ensemble du projet est hébergé sur GitHub, qui est un gestionnaire de versions basé sur Git. Il permet d’apporter des contributions au projet de façon sécurisée, même lorsque plusieurs utilisateurs souhaitent travailler en même temps, car il conserve l’historique de tous les changements et permet de revenir à un état précédent du projet à tout moment.

# Prérequis

Vous devez disposer de Git et d’un compte GitHub, ainsi que d’un environnement de développement vous permettant d’utiliser PHP 8 et une base de données MySQL.

# Etapes à suivre pour contribuer

## 1. Récupérer le projet en local

Le dépôt GitHub est destiné à stocker et archiver des ressources, pas à permettre directement leur modification. Pour pouvoir participer au projet, la première étape consiste donc à récupérer l’ensemble du code et des éléments du projet sur en environnement local, où il pourra ensuite être modifié via un éditeur de code, et testé localement, avant de finalement être renvoyé sur GitHub en incluant les modifications.

Pour récupérer le projet, vous devez avoir initialisé sur votre poste de travail un dépôt git, et vous placer à l’intérieur pour cloner le dépôt distant contenant le projet via la commande suivante :

> git clone https://github.com/AuroreFunes/OCP8_ToDoList.git

## 2. Créer une nouvelle branche

Toutes les nouvelles modifications doivent être faites sur une branche distincte de la branche principale. C’est ce principe qui donne à git toute son utilité.
Placez-vous sur la branche principale, et assurez-vous qu’elle soit à jour par rapport au dépôt distant, c’est-à-dire que votre branche locale contienne bien les dernières modifications reportées sur la branche principale distante. Pour cela, placez-vous sur votre branche principale locale, et utilisez la commande suivante pour tirer la branche principale distante :

> git checkout main
> git pull origin main

Créez ensuite une nouvelle branche locale, en veillant à lui donner un nom explicite et en accord avec la fonctionnalité que vous allez développer, ou les correctifs que vous allez réaliser.
Attention à bien respecter le principe une branche = un sujet.

> git checkout -b nouvelleBranche

Vous pouvez ensuite effectuer les modifications sur votre nouvelleBranche locale, puis les tester. Une fois qu’elles sont validées, enregistrez les modifications en réalisant un commit contenant vos modifications.

La commande suivante permet d’ajouter des fichiers au commit :

> git commit -a fichierAAjouter

Puis pour créer effectivement le commit, utilisez la commande suivante :

> git commit -m "message décrivant les modifications effectuées"

## 3. Envoyer les modifications sur Git

Une fois votre branche prête, le dernier commit effectué, et les modifications testées et validées, l’étape suivante consiste à envoyer le dépôt distant afin de pouvoir le partager avec le reste de l’équipe.
Pour cela, il y a deux étapes à faire.

La première consiste à envoyer la nouvelle branche sur le dépôt :

> git push origin brancheAPousser

La nouvelle branche est maintenant disponible sur le dépôt et peut être récupérée par les autres membres de l’équipe. Cependant pour l’intégrer au projet, et pouvoir éventuellement la déployer dans un environnement de production, il faut la fusionner avec la branche principale. Il faut pour cela effectuer une « merge request », qui devra être validée par la personne en charge de gérer le dépôt.

# Implémentation des services

Le code métier ne doit pas se trouver dans les contrôleurs : à chaque nouvelle fonctionnalité, créez un service qui réalise le traitement.

Les services étendent de la classe ServiceHelper, ou d’un autre service « helper » qui content des méthodes spécifiques au sujet traité et qui doit lui-même étendre de ServiceHelper. Cela permet d’éviter de dupliquer des fonctions qui peuvent être communes à plusieurs services, et facilite ainsi la maintenance de l’application.


Il doit être conçu toujours de la même manière :
- Un unique point d’entrée qui appelle successivement les méthodes utiles au traitement :
    * Appel de la méthode « initHelper() » pour initialiser le service (notamment le statut et les variables contenant les erreurs),
    * Vérification des données fournies,
    * Exécution du traitement si les données fournies sont valides.
- Le service retourne l’instance de lui-même à la fin de son exécution :
    * Le statut a été laissé à false si une erreur s’est produite au cours du traitement, sinon penser à le faire passer à true.
    * Si des erreurs se sont produites, elles doivent être ajoutées à la collection « errMessages » pendant le traitement afin de pouvoir être affichées.

Le contrôleur peur ensuite afficher un message de succès ou lister les erreurs selon le statut du service, et si besoin rediriger vers une autre page.

# Mettre à jour une entité

Utiliser la commande de Symfony afin de générer automatiquement le ou les champs et les accesseurs :

php bin/console make:entity MonEntité

Important :
Si le champ ajouté doit être « non nullable », il faut procéder en deux étapes afin de ne pas avoir d’erreur lors des migrations :
- Créer le champ en le laissant nullable et jouer la migration
- Jouer une requête d’Update sur la table concernée afin de remplir les champs avec une valeur par défaut
    * UPDATE maTable SET monChamp = ‘maValeur’
        - Penser à préciser une clause WHERE s’il est nécessaire de filtrer les données
        - Penser à réaliser l’opération sur la table de tests (ou à la vider avant de jouer les migrations)
- Rendre le champ non nullable et créer et jouer une deuxième migration

Exemple :
Si dans les tâches le champ « avancement » n’existe pas encore et qu’on veut l’ajouter avec la condition suivante :
- La valeur par défaut est de 0 pour les tâches déjà existantes non terminées et les futures nouvelles tâches
- Les tâches existantes déjà terminées doivent avoir un avancement à 100

1. Modifier l’entité Task pour ajouter le champ « completion » nullable.
2. Jouer la migration pour ajouter le champ dans la base de données
3. Mettre à jour les données. Le plus simple est de jouer deux requêtes avec une clause WHERE
    * UPDATE task SET completion = 0 WHERE is_done = 0
    * UPDATE task SET completion = 100 WHERE is_done = 1
4. Rendre le champ non nullable et créer puis jouer la nouvelle migration

Mettre à jour les services et les tests si nécessaire (voir si en déclarant la variable maVariable = x dans l’entité, on a bien la valeur quand on créé un nouvel objet)

# Qualité du code

## Ecriture du code

Le projet est suivi par Codacy, outil qui a pour but de vérifier la qualité du code : syntaxe, respect des PSR, absence de duplication et de code inutilisé…
A chaque nouvelle branche poussée sur le dépôt distant, vérifiez la note attribuée par Codacy et effectuez les éventuelles corrections nécessaires avant de faire la merge request vers la branche principale. La note ne devrait pas aller en dessous de C, et idéalement B. Un score plus bas doit vous inviter à revoir le contenu de votre branche afin d’améliorer sa qualité avant d’envisager de la fusionner avec la branche principale.

## Tests de l'application

### Absence de régression

Pour s’assurer de la qualité de l’application et l’absence de régression, il est important de jouer tous les tests unitaires et de s’assurer qu’ils soient valides avant de fusionner la branche modifiée avec la branche principale.
Pour cela, utilisez la commande :

> php bin/phpunit

Tous les tests en erreurs doivent être corrigés afin d’être valides. Assurez-vous qu’un test ne soit pas en erreur à cause d’un manque de fixtures, et si nécessaires rajouter les données requises.

### Suivi des nouvelles fonctionnalités

Lors du développement de nouvelles fonctionnalités, des tests unitaires (basés sur les services) et fonctionnels (pour vérifier que le contrôleur donne le bon retour et que les pages s'affichent correctement) doivent être implémentés, afin de permettre le suivi de la qualité de l’application, et l’absence de régression lors d’implémentations futures.
