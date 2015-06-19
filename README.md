Becklyn Database Dump Bundle
============================

The Becklyn Database Dump Bundle is a [Symfony 2](http://www.symfony.com/) bundle which allows you to easily backup your databases directly from the CLI simply by calling
 `symfony becklyn:db:dump`.
Currently only supporting MySQL/MariaDB databases this bundle makes use of the pre-installed `mysqldump` (which comes with MySQL/MariaDB) tool for creating backups.
All backups will be automatically GZip'ed to preserve as much disk space as possible.
 
A few of the many possible usage scenarios for this bundle are the creation of a very basic backup service which could be run by something like a Cronjob.
 Another one is to easily create a backup of the database when deploying a new version of your app which may touch critical/important data. Better safe than sorry.
 
It works great together with the [Doctrine's Migrations Bundle](https://github.com/doctrine/DoctrineMigrationsBundle).


Installation
------------

To install this bundle into your Symfony 2 application execute the following composer command:

```bash
$ composer require becklyn/database-dump-bundle
```

Requirements
------------

In order to use the Database Dump Bundle additional system dependencies are required to be installed:

- mysqldump
- gzip


Supported Databases
-------------------

For now we're currently supporting the following databases:
- MySQL
- MariaDB


Usage
-----

```bash
$ symfony becklyn:db:dump
```

By default Becklyn Database Dump Bundle looks up the Symfony configuration and takes all registered connections and backs them up.
 An brief introduction on how to use multiple connections in Symfony 2 can be found in the [Symfony Cookbook](http://symfony.com/doc/current/cookbook/doctrine/multiple_entity_managers.html).
                    
> **Note**: Becklyn Database Dump Bundle doesn't require multiple connections configured. Internally there is no difference between one connection or five connections as Symfony
> names its default connection `default`. That means you can configure Becklyn Database Dump Bundle to only backup the connection named `default` while only one connection is supplied. 


Configuration
-------------

In all easiness of the bundle's defaults we're also providing some configuration options.


Config.yml based configuration
------------------------------

A different way than passing all configuration values as parameters to the dump command is to use the available configuration inside the `config.yml`.

### Connections

Given the following scenario where three connections (`default`, `customer` and `backup`) are set up in your `config.yml` and you want to backup only `default` and `customer`:

```yaml
# Doctrine Configuration
doctrine:
    dbal:
        default_connection: default
        connections:
            default:
                # ...
            customer:
                # ...
            backup:
                # ...
```

You simply add the following configuration into your `config.yml` which tells the Database Dump Bundle exactly which connections it needs to backup.

```yaml
becklyn_database_dump:
    connections:
        - default
        - customer
```

That's it. Now, all you need to do to start backing up your databases is calling the dump command:

```bash
$ symfony becklyn:db:dump
```


### Backup directory

The default location to store the database backups is `%kernel.root_dir%/var/db_backups/`, which is configurable by adding the following settings into your `config.yml` file:

```yaml
becklyn_database_dump:
    directory: /the/new/backup/location/
```


### Full example configuration

A full example configuration for Becklyn Database Dump Bundle can look like this:

```yaml
becklyn_database_dump:
    connections:
        - default
        - customer
    directory: /the/new/backup/location/
```


CLI Arguments
-------------

The following optional parameters are available for the `symfony becklyn:db:dump` command:


| Parameter       | Short | Description                                                                                                                                                                                                                      |
|-----------------|-------|----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------|
| `--connections` | `-c`  | Dumps the provided database connections.<br>Multiple connection identifiers can be selected by specifying this parameter multiple times. An alternative syntax for this is to separate each connection identifier by comma (`,`) |
| `--path`        | `-p`  | The folder path where the .sql file will be saved. Defaults to `%kernel.root_dir%/var/db_backups/`                                                                                                                               |


Example usage:

```bash
$ symfony becklyn:db:dump --connections=default --connections=customer --path=/var/backups/symfony/db/
$ symfony becklyn:db:dump --connections=default,customer --path=/var/backups/symfony/db/
```

Overriding configuration values
-------------------------------

An important concept of Becklyn Database Dump Bundle is that you can easily override previously configured values.
Configuration values are read in the following order where one can override another:

1. config.yml
2. CLI arguments

That means that you can specify which connections should be backed up regardless of what is configured in the `config.yml` by supplying the corresponding CLI arguments.

With our example `config.yml` given from above:

```yaml
becklyn_database_dump:
    connections:
        - default
        - customer
```

we can execute the following command:

```bash
$ symfony becklyn:db:dump --connections=default
```

and it would only backup the database associated with the `default` connection.


Backup files
------------

To avoid name clashes when using multiple connections that may point to different databases with the same name, the Database Dump Bundle uses the following file name schema:

```php
Y-m-d_H-i_backup_{$connectionIdentifier}__{$databaseName}.sql.gz
```


License
-------

Information about the license used for the Becklyn Database Dump Bundle can be found in the [LICENSE](license) file.
