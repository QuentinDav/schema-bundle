# QdSchemaBundle

**QdSchemaBundle** is a Symfony bundle for managing and serving database schemas with integrated frontend assets.  
It provides a collaborative interface for teams to view, edit, and maintain their database structure directly within a Symfony project.

---

## Installation

Since this bundle is not installed via Symfony Flex, you will need to register it manually.

### 1. Install the bundle via Composer
```bash
composer require qd/schema-bundle
```

### 2. Enable the bundle manually

Open your `config/bundles.php` file and add the following entry:

```php
<?php

return [
    // ...
    Qd\SchemaBundle\QdSchemaBundle::class => ['all' => true],
];
```

---

## Configuration

### 1. Register the routes

Add the following configuration in your `config/routes.yaml` file:

```yaml
qd_schema:
    resource: '@QdSchemaBundle/Resources/config/routes.yaml'
```

This will load all the routes provided by QdSchemaBundle (both API and frontend routes).

---

## Database setup

To install the database entities used by the bundle, execute the following command:

```bash
php bin/console qd:schema:install --force
```

This command will:
- Create the required database tables for QdSchemaBundle.
- Synchronize schema entities used internally by the bundle.

⚠️ The `--force` flag will apply the changes directly to your database.  
You may run the command without `--force` first to preview what will be executed.

---

## 🧩 Frontend assets

All frontend assets are already compiled and shipped within the bundle under:

src/Resources/public/schema/

To make these assets available in your application’s public directory, run:

```bash
php bin/console assets:install --symlink --relative
```

This command will copy or symlink the bundle’s assets to your project’s `public/` directory.

You should then be able to access the QdSchema frontend (for example) via:

http://your-app.local/schema-doc/schema

---

## 🧠 Usage

Once installed:
- Open the `/schema-doc/schema` route in your browser to view the schema management interface.
- Use the command-line tools to update or refresh schema data when needed.

---

## 🧰 Available Commands

| Command | Description |
|----------|-------------|
| `php bin/console qd:schema:install [--force]` | Installs or updates the bundle’s database entities |

---

## 🤝 Contributing

If you'd like to contribute improvements or report bugs, please open an issue or submit a pull request on GitHub.

---

## 📄 License

This bundle is distributed under the MIT License.

© Quentin David – 2025
