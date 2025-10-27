<div align="center">

# 🗄️ QD Schema Bundle

### Interactive Database Schema Documentation & Management for Symfony

[![Latest Version](https://img.shields.io/packagist/v/qd/schema-bundle.svg?style=flat-square)](https://packagist.org/packages/qd/schema-bundle)
[![Total Downloads](https://img.shields.io/packagist/dt/qd/schema-bundle.svg?style=flat-square)](https://packagist.org/packages/qd/schema-bundle)
[![License](https://img.shields.io/packagist/l/qd/schema-bundle.svg?style=flat-square)](https://packagist.org/packages/qd/schema-bundle)
[![PHP Version](https://img.shields.io/packagist/php-v/qd/schema-bundle.svg?style=flat-square)](https://packagist.org/packages/qd/schema-bundle)

[Features](#-features) • [Installation](#-installation) • [Screenshots](#-screenshots) • [Documentation](#-documentation) • [Contributing](#-contributing)

</div>

---

## 📖 About

**QdSchemaBundle** transforms your Symfony application's database schema into an interactive, collaborative documentation platform. Built specifically for Symfony and Doctrine ORM, it automatically extracts your database structure and provides a beautiful Vue 3 interface for teams to explore, document, and track schema evolution over time.

Perfect for:
- 📊 **Development Teams** - Keep everyone synchronized on database changes
- 🔄 **Schema Evolution** - Track and version your database structure across releases
- 📝 **Documentation** - Auto-generated, always up-to-date schema docs
- 🤝 **Collaboration** - Comment and discuss entity changes with your team
- 🔍 **Discovery** - Visualize entity relationships with interactive ER diagrams

---

## ✨ Features

### 🎯 Core Features
- **🔄 Automatic Schema Extraction** - Automatically reads your Doctrine entities metadata
- **📊 Interactive ER Diagrams** - Visualize entity relationships with Vue Flow
- **🏷️ Release Management** - Version and track schema changes with semantic versioning
- **📝 Collaborative Comments** - Comment on entities and fields with @mentions
- **🔍 Smart Search** - Quickly find entities by name or namespace
- **📈 Change Tracking** - Detailed diffs between schema versions
- **📤 Markdown Export** - Export releases as beautiful markdown documentation
- **🎨 Multiple Views** - Graph, cards, and list views for different workflows
- **⚡ Zero Configuration** - Works out of the box with Doctrine ORM
- **🔐 Role-Based Access** - Built-in security with Symfony roles

### 🚀 Technical Highlights
- Built with **Vue 3** (Composition API) + **Pinia** for the frontend
- Automatic layout with **ELK.js** for beautiful graph visualizations
- Full **Symfony 6.4+ & 7.0+** compatibility
- **PHP 8.1+** with modern features
- Comprehensive **PHPUnit** test coverage
- **DTOs with validation** for API requests
- Transaction-safe schema snapshots

---

## 📦 Installation

### Requirements
- PHP 8.1 or higher
- Symfony 6.4 or 7.0+
- Doctrine ORM 3.5+

### Step 1: Install via Composer
```bash
composer require qd/schema-bundle
```

### Step 2: Enable the bundle

Add to your `config/bundles.php`:

```php
<?php

return [
    // ...
    Qd\SchemaBundle\QdSchemaBundle::class => ['all' => true],
];
```

### Step 3: Load the routes

Add to your `config/routes.yaml`:

```yaml
qd_schema:
    resource: '@QdSchemaBundle/Resources/config/routes.yaml'
```

### Step 4: Install database tables

```bash
php bin/console qd:schema:install --force
```

### Step 5: Install frontend assets

```bash
php bin/console assets:install --symlink --relative
```

### Step 6: Access the interface

Open your browser at: `http://your-app.local/schema-doc`

---

## 📸 Screenshots

*Coming soon - Interactive ER diagrams, Release timeline, Comment system*

---

## 🎯 Use Cases

### For Development Teams
- **Onboarding**: New developers instantly understand your database structure
- **Code Reviews**: Visualize the impact of schema changes in PRs
- **Planning**: Discuss schema modifications with interactive diagrams

### For Documentation
- **Auto-Generated Docs**: No more outdated schema documentation
- **Export to Markdown**: Generate beautiful docs for your wiki/readme
- **Historical Reference**: See how your schema evolved over time

### For DevOps & CI/CD
- **Schema Versioning**: Track database changes alongside application versions
- **Migration Planning**: Compare releases to plan data migrations
- **Rollback Support**: Know exactly what changed in each release

---

## Security & Roles

QdSchemaBundle requires users to have the `ROLE_QD_EDIT` role to access certain features:

- **Comment management**: Users with `ROLE_QD_EDIT` will appear in the user list for @mentions in comments
- **Comment deletion**: Only the comment author or users with `ROLE_ADMIN` can delete comments

To grant access to a user, add the role to your User entity:

```php
class User implements UserInterface
{
    private array $roles = ['ROLE_USER', 'ROLE_QD_EDIT'];

}
```

Or via your user management system (e.g., EasyAdmin, custom admin panel).

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

## 🔧 Usage

### Creating a Release

Creating a release captures the current state of your database schema:

```bash
curl -X POST http://your-app.local/schema-doc/api/releases \
  -H "Content-Type: application/json" \
  -d '{"version_type": "minor", "description": "Added user profile fields"}'
```

Or use the web interface at `/schema-doc` to create releases with one click.

### Comparing Releases

```bash
GET /schema-doc/api/releases/compare/{id1}/{id2}
```

Returns detailed diffs showing:
- Added entities
- Removed entities
- Modified fields and relations
- Change percentages

### Exporting Documentation

```bash
GET /schema-doc/api/releases/{id}/export/markdown
```

Generates a beautiful markdown document with:
- Release summary
- Entity changes with emojis
- Field and relation details
- Full schema snapshots

---

## 📚 Documentation

### API Endpoints

| Endpoint | Method | Description |
|----------|--------|-------------|
| `/schema-doc/api/schema` | GET | Get current live schema |
| `/schema-doc/api/releases` | GET | List all releases |
| `/schema-doc/api/releases` | POST | Create new release |
| `/schema-doc/api/releases/{id}` | GET | Get release details |
| `/schema-doc/api/releases/compare/{id1}/{id2}` | GET | Compare two releases |
| `/schema-doc/api/releases/{id}/export/markdown` | GET | Export as markdown |
| `/schema-doc/api/comments` | GET/POST/DELETE | Manage comments |
| `/schema-doc/api/users` | GET | Get users with ROLE_QD_EDIT |

### CLI Commands

| Command | Description |
|----------|-------------|
| `php bin/console qd:schema:install [--force]` | Install bundle database tables |

---

## 🎨 Architecture

### Backend (Symfony/PHP)
- **Controllers**: RESTful API with DTOs and validation
- **Services**: SchemaExtractor, SchemaDiff, VersioningService, SnapshotService
- **Entities**: Release, Snapshot, Comment (Doctrine ORM)
- **Security**: Role-based access control with Symfony Security

### Frontend (Vue 3)
- **Framework**: Vue 3 with Composition API
- **State**: Pinia stores for schema, releases, comments
- **Visualization**: Vue Flow for ER diagrams, ELK.js for auto-layout
- **Routing**: Vue Router for SPA navigation
- **Build**: Vite for fast development and optimized production builds

---

## 🔐 Security

### Roles
- `ROLE_QD_EDIT`: Required to appear in mention lists
- `ROLE_ADMIN`: Can delete any comment

### Authorization
- Comment deletion: Only author or admin
- Entity validation: Checks against Doctrine metadata
- SQL injection protection: Parameterized queries throughout

---

## 🧪 Testing

Run the test suite:

```bash
vendor/bin/phpunit
```

Current coverage:
- ✅ SchemaDiff service (7 tests)
- ✅ VersioningService (9 tests)
- 🎯 More tests coming soon

---

## 🗺️ Roadmap

- [ ] Field-level comments
- [ ] Custom metadata fields
- [ ] Webhooks for external integrations
- [ ] Export to SQL/Doctrine migrations
- [ ] Dark mode
- [ ] Advanced filtering and search
- [ ] API authentication tokens
- [ ] Slack/Discord notifications

---

## 🤝 Contributing

Contributions are welcome! Here's how:

1. Fork the repository
2. Create a feature branch (`git checkout -b feature/amazing-feature`)
3. Commit your changes (`git commit -m 'Add amazing feature'`)
4. Push to the branch (`git push origin feature/amazing-feature`)
5. Open a Pull Request

Please ensure:
- All tests pass (`vendor/bin/phpunit`)
- Code follows PSR-12 standards
- New features include tests
- Update documentation as needed

---

## 💬 Support

- **Issues**: [GitHub Issues](https://github.com/quentindavid/qd-schema-bundle/issues)
- **Discussions**: [GitHub Discussions](https://github.com/quentindavid/qd-schema-bundle/discussions)
- **Email**: quentin.dav33@gmail.com

---

## 📄 License

This bundle is open-source software licensed under the [MIT License](LICENSE).

---

## 🙏 Acknowledgments

Built with:
- [Symfony](https://symfony.com) - The PHP framework for web applications
- [Doctrine ORM](https://www.doctrine-project.org/) - The database abstraction layer
- [Vue 3](https://vuejs.org/) - The Progressive JavaScript Framework
- [Vue Flow](https://vueflow.dev/) - Interactive node-based diagrams
- [ELK.js](https://github.com/kieler/elkjs) - Automatic graph layout
- [Pinia](https://pinia.vuejs.org/) - Vue state management

---

<div align="center">

**Made with ❤️ by [Quentin David](https://github.com/quentindavid)**

⭐ Star this repository if you find it useful!

</div>
