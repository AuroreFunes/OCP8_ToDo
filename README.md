# OCP8_ToDo
OpenClassrooms - Project 8 - ToDoList

It's an application for managing your daily tasks.

## Prerequisites

You must have PHP 8.x and a database that you can manage freely. You must also have Composer installed and have a terminal to use the command lines.


## Installation procedure

First, copy all the project files.
You can use :
> git clone https://github.com/AuroreFunes/OCP8_ToDoList.git

### Dependencies

Go to the project directory to launch the installation of Symfony and its dependencies :
> composer install


### Environment settings

Rename the ".env.example" file to ".env" and complete it as needed.


### Symfony server

You have to make your database engine work.

Then, go to the project directory to start the Symfony server with the command :
> symfony server:start


### Database

* Edit the ".env" file to fill in the connection information to your database :
> DATABASE_URL=

* Create the database using this command :
> php bin/console doctrine:database:create

* Then create the database structure by launching the migrations :
> php bin/console doctrine:migrations:migrate

* Create the first data from the Fixtures :
> php bin/console doctrine:fixtures:load


## Tests

You can use tests with phpunit to make sure the application is working properly.  

* Create the test database using this command :
> symfony console doctrine:database:create --env=test

* Then create the database structure by launching the migrations :
> symfony console doctrine:migrations:migrate -n --env=test

* Create the data for testing from the Fixtures :
> symfony console doctrine:fixtures:load --env=test  

If the results are not as expected :  

* Replay the fixtures  

* Empty the caches with the command :
> php bin/console cache:clear --env=test


[![Codacy Badge](https://app.codacy.com/project/badge/Grade/66ba6097910049b490705b16bcbeb09d)](https://app.codacy.com/gh/AuroreFunes/OCP8_ToDoList/dashboard?utm_source=gh&utm_medium=referral&utm_content=&utm_campaign=Badge_grade)