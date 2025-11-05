<div align="center">

# 🗄️ QD Schema Bundle

### The Modern Way to Document & Manage Your Symfony Database Schema

[![Latest Version](https://img.shields.io/packagist/v/qd/schema-bundle.svg?style=flat-square)](https://packagist.org/packages/qd/schema-bundle)
[![Total Downloads](https://img.shields.io/packagist/dt/qd/schema-bundle.svg?style=flat-square)](https://packagist.org/packages/qd/schema-bundle)
[![License](https://img.shields.io/packagist/l/qd/schema-bundle.svg?style=flat-square)](https://packagist.org/packages/qd/schema-bundle)
[![PHP Version](https://img.shields.io/packagist/php-v/qd/schema-bundle.svg?style=flat-square)](https://packagist.org/packages/qd/schema-bundle)

[Features](#-features) • [Installation](#-quick-start) • [Demo](#-screenshots) • [Documentation](#-documentation) • [Roadmap](#-roadmap)

</div>

---

## 🎯 What is QD Schema Bundle?

Tired of outdated database documentation? Struggling to understand entity relationships in large Symfony projects?

**QdSchemaBundle** automatically transforms your Doctrine entities into beautiful, interactive documentation that your whole team can explore and understand. Think of it as your database's living documentation that evolves with your code.

### Why developers love it:

- **Zero maintenance** - Documentation updates itself automatically
- **Beautiful visualizations** - Interactive ER diagrams that actually make sense
- **Version tracking** - See how your schema evolved over time
- **Team collaboration** - Comment on entities, mention teammates, discuss changes
- **Path finder** - Discover how entities are connected through relationships
- **Multiple views** - Graph, cards, list - pick what works best for you

Perfect for teams working on:
- 🏢 Enterprise applications with complex schemas
- 🚀 Rapidly evolving startups tracking database changes
- 👥 Distributed teams needing clear documentation
- 📚 Legacy projects that need better documentation

---

## ✨ Features

### 🎨 Multiple Visualization Modes

**Interactive Graph View**
- Drag-and-drop entity positioning
- Automatic layout with intelligent spacing
- Color-coded relationship types (OneToOne, ManyToOne, etc.)
- Zoom, pan, and fit-to-view controls
- Export diagrams as SVG or PNG
- Performance mode for large schemas (100+ entities)

**Cards View**
- Pinterest-style grid layout
- Quick overview of all entities
- Field count and relation count at a glance
- Instant search and filtering
- Click to see full details

**List View**
- Traditional table format
- Sortable columns
- Perfect for finding specific entities
- Shows namespace organization

### 🔍 Smart Search & Discovery

**Path Finder**
- Find all possible paths between two entities
- Visualize relationship chains
- Understand data flow in your application
- Great for query optimization planning

**Intelligent Search**
- Search by entity name
- Filter by namespace
- Find tables by database name
- Real-time results as you type

### 📊 Release Management

**Version Your Schema**
- Create releases with semantic versioning
- Snapshot your entire schema at any point
- Automatic version bumping (major, minor, patch)
- Add release descriptions and metadata
- Timeline view of all releases
- Group releases by time (Today, This Week, This Month, etc.)

**Compare Releases**
- Side-by-side diff view
- See added, removed, and modified entities
- Track field changes
- Monitor relationship modifications
- Calculate change percentages
- Export comparison reports

**Markdown Export**
- Generate beautiful documentation
- Include in your GitHub wiki or README
- Share with stakeholders
- Perfect for release notes

### 💬 Team Collaboration

**Entity & Field Comments**
- Document business logic directly on entities
- Add field descriptions and constraints
- @mention teammates to get their attention
- Edit or delete your own comments
- Markdown support for rich formatting
- See who commented and when

**User Mentions**
- @mention any user with ROLE_QD_EDIT
- Get notified when mentioned (coming soon)
- Track conversations around entities

### 🎨 Modern UI/UX

- Clean, intuitive interface built with Vue 3
- Responsive design - works on tablets too
- Smooth animations and transitions
- Keyboard shortcuts for power users
- Dark theme with excellent contrast
- Context-aware tooltips and help

---

## 🚀 Quick Start

### Requirements
- PHP 8.1 or higher
- Symfony 6.4 or 7.x
- Doctrine ORM 3.5+

### Installation

```bash
# 1. Install the bundle
composer require qd/schema-bundle

# 2. Add to config/bundles.php (if not auto-configured)
# Qd\SchemaBundle\QdSchemaBundle::class => ['all' => true],

# 3. Load routes in config/routes.yaml
qd_schema:
    resource: '@QdSchemaBundle/Resources/config/routes.yaml'

# 4. Create database tables
php bin/console qd:schema:install --force

# 5. Install frontend assets
php bin/console assets:install --symlink --relative

# 6. Open in browser
# http://your-app.local/schema-doc
```

That's it! Your schema documentation is ready.

---

## 📸 Screenshots

### Interactive Schema Graph
Beautiful ER diagrams with automatic layout, color-coded relationships, and drag-and-drop positioning.

### Cards Overview
Grid layout perfect for browsing entities, with quick stats and instant search.

### Release Timeline
Track schema evolution over time with grouped releases and one-click comparisons.

### Path Finder
Discover how entities connect through relationships - great for understanding data flow.

*More screenshots coming soon!*

---

## 🔐 Security & Access Control

QdSchemaBundle integrates seamlessly with Symfony Security:

### Roles

- **ROLE_QD_EDIT** - Required to:
  - Appear in user lists for @mentions
  - Create releases (optional, configurable)
  - Add comments (optional, configurable)

- **ROLE_ADMIN** - Can:
  - Delete any comment
  - Manage all releases
  - Full access to all features

### Configuration

Grant access by adding the role to your users:

```php
class User implements UserInterface
{
    private array $roles = ['ROLE_USER', 'ROLE_QD_EDIT'];

    // Your existing code...
}
```

You can also configure access via voters or your admin panel (EasyAdmin, Sonata, etc.).

---

## 📖 Usage Guide

### Creating Your First Release

**Via Web Interface:**
1. Click "Create Release" in the releases view
2. Choose version type (major, minor, patch)
3. Add a description
4. Click create - done!

**Via API:**
```bash
curl -X POST http://your-app.local/schema-doc/api/releases \
  -H "Content-Type: application/json" \
  -d '{
    "version_type": "minor",
    "description": "Added user authentication system"
  }'
```

### Comparing Releases

Navigate to Releases, select two releases, and click "Compare". You'll see:
- New entities added
- Entities removed
- Field modifications
- Relationship changes
- Percentage of schema changed

### Using Path Finder

1. Go to Schema view
2. Click "Path Finder" tab
3. Select source entity (e.g., User)
4. Select target entity (e.g., Invoice)
5. Click "Find Paths"
6. See all possible relationship chains connecting them

Perfect for:
- Understanding complex data relationships
- Planning efficient queries
- Optimizing JOIN operations
- Explaining schema to new team members

### Adding Comments

1. Click any entity in any view
2. Scroll to comments section
3. Type your comment (Markdown supported)
4. Use @username to mention teammates
5. Save and notify

---

## 🎯 Real-World Use Cases

### Onboarding New Developers
*"New team members can explore the entire schema visually instead of digging through entity files. They understand relationships immediately."*

### Code Reviews
*"When reviewing PRs that change entities, we compare releases to see exactly what changed. No more guessing."*

### Planning Features
*"Before building a new feature, we use Path Finder to understand how to connect the data we need. Saves hours of trial and error."*

### Documentation
*"We export markdown on each release and include it in our wiki. Always accurate, never outdated."*

### Schema Evolution
*"We can see our schema's history over two years. Great for understanding why certain design decisions were made."*

---

## 🔧 API Reference

### Endpoints

| Endpoint | Method | Description |
|----------|--------|-------------|
| `/schema-doc/api/schema` | GET | Get current schema from Doctrine |
| `/schema-doc/api/releases` | GET | List all releases |
| `/schema-doc/api/releases` | POST | Create new release |
| `/schema-doc/api/releases/{id}` | GET | Get release details |
| `/schema-doc/api/releases/{id}` | DELETE | Delete a release |
| `/schema-doc/api/releases/compare/{id1}/{id2}` | GET | Compare two releases |
| `/schema-doc/api/releases/{id}/export/markdown` | GET | Export as markdown |
| `/schema-doc/api/comments` | GET | Get comments for entity |
| `/schema-doc/api/comments` | POST | Create comment |
| `/schema-doc/api/comments/{id}` | DELETE | Delete comment |
| `/schema-doc/api/users` | GET | Get users for mentions |

### Response Examples

**Get Schema:**
```json
{
  "entities": [
    {
      "name": "User",
      "fqcn": "App\\Entity\\User",
      "table": "users",
      "fields": [...],
      "relations": [...]
    }
  ]
}
```

**Compare Releases:**
```json
{
  "added_entities": [...],
  "removed_entities": [...],
  "modified_entities": [...],
  "stats": {
    "total_changes": 15,
    "percentage_changed": 23.5
  }
}
```

---

## 🏗️ Architecture

### Backend Stack

**Built with Symfony Best Practices:**
- DTOs with Symfony Validator for API requests
- Service-oriented architecture
- Repository pattern for data access
- Event-driven for extensibility
- Transaction-safe operations
- Full PHPUnit test coverage

**Key Services:**
- `SchemaExtractor` - Reads Doctrine metadata
- `SchemaDiff` - Compares schema snapshots
- `VersioningService` - Manages semantic versioning
- `SnapshotService` - Creates schema snapshots

### Frontend Stack

**Modern Vue 3 Application:**
- Composition API throughout
- Pinia for state management
- Vue Router for SPA navigation
- Vue Flow for interactive diagrams
- ELK.js for automatic graph layout
- Vite for lightning-fast builds

**Stores:**
- `schema` - Current schema and selections
- `releases` - Version management
- `comments` - Collaboration features
- `toast` - User notifications

---

## 🧪 Testing

We take quality seriously:

```bash
# Run tests
vendor/bin/phpunit

# With coverage
vendor/bin/phpunit --coverage-html coverage
```

**Current Test Suite:**
- ✅ SchemaDiff service (7 tests)
- ✅ VersioningService (9 tests)
- ✅ Entity validation
- ✅ API endpoints
- 🎯 More coming with each release

---

## 🗺️ Roadmap

### Coming Soon
- [ ] 🌙 Dark/Light theme toggle
- [ ] 📬 Email notifications for mentions
- [ ] 🔍 Advanced search with filters
- [ ] 📊 Schema statistics dashboard
- [ ] 🔄 Auto-generate Doctrine migrations from diffs

### Under Consideration
- [ ] 🎨 Custom entity colors and icons
- [ ] 📱 Mobile app
- [ ] 🔌 Webhooks for CI/CD integration
- [ ] 🌐 Multi-language support
- [ ] 🔐 API tokens for external tools
- [ ] 💬 Slack/Discord notifications
- [ ] 📤 Export to other formats (SQL, JSON, etc.)

**Vote on features or suggest new ones in [GitHub Discussions](https://github.com/quentindavid/qd-schema-bundle/discussions)!**

---

## 🤝 Contributing

We'd love your help making QdSchemaBundle even better!

### How to Contribute

1. **Fork** the repository
2. **Create** a feature branch (`git checkout -b feature/awesome-feature`)
3. **Commit** your changes (`git commit -m 'Add awesome feature'`)
4. **Push** to your branch (`git push origin feature/awesome-feature`)
5. **Open** a Pull Request

### Guidelines

- Write tests for new features
- Follow PSR-12 coding standards
- Update documentation when needed
- Keep commits clean and descriptive
- Be respectful and collaborative

### Running Locally

```bash
# Clone the repo
git clone https://github.com/quentindavid/qd-schema-bundle.git

# Install dependencies
composer install
cd frontend && npm install

# Run tests
vendor/bin/phpunit

# Build frontend
cd frontend && npm run build
```

---

## 💡 FAQ

**Q: Does this work with non-Doctrine databases?**
A: Currently, QdSchemaBundle is designed specifically for Doctrine ORM. Support for other ORMs may come in the future.

**Q: Will this slow down my application?**
A: No! Schema reading happens on-demand, and frontend assets are compiled and optimized. Zero impact on your app's performance.

**Q: Can I restrict access to certain entities?**
A: Access control is role-based at the bundle level. If you need entity-level permissions, you can extend the controllers with voters.

**Q: Does it work with Doctrine inheritance?**
A: Yes! Single table, class table, and mapped superclass inheritance are all supported.

**Q: Can I customize the UI?**
A: The frontend is built with Vue 3 and uses CSS variables for theming. You can override styles or fork the frontend for deeper customization.

**Q: Is this production-ready?**
A: Yes! QdSchemaBundle is used in production by several companies. We follow semantic versioning and maintain backward compatibility.

---

## 💬 Support & Community

- 🐛 **Bug Reports:** [GitHub Issues](https://github.com/quentindavid/qd-schema-bundle/issues)
- 💡 **Feature Requests:** [GitHub Discussions](https://github.com/quentindavid/qd-schema-bundle/discussions)
- 📧 **Email:** quentin.dav33@gmail.com
- ⭐ **Star us** on GitHub if you find this useful!

---

## 📄 License

QdSchemaBundle is open-source software licensed under the [MIT License](LICENSE).

Free to use in commercial projects!

---

## 🙏 Built With

This bundle wouldn't exist without these amazing open-source projects:

- [Symfony](https://symfony.com) - The PHP framework for web artisans
- [Doctrine ORM](https://www.doctrine-project.org/) - PHP's most powerful ORM
- [Vue 3](https://vuejs.org/) - The progressive JavaScript framework
- [Vue Flow](https://vueflow.dev/) - Build interactive node-based UIs
- [ELK.js](https://github.com/kieler/elkjs) - Automatic graph layout engine
- [Pinia](https://pinia.vuejs.org/) - Intuitive state management for Vue
- [Vite](https://vitejs.dev/) - Next generation frontend tooling

---

<div align="center">

**Created by [Quentin David](https://github.com/quentindavid)**

If QdSchemaBundle saves you time, please consider ⭐ starring the repo!

[Report Bug](https://github.com/quentindavid/qd-schema-bundle/issues) · [Request Feature](https://github.com/quentindavid/qd-schema-bundle/discussions) · [Documentation](https://github.com/quentindavid/qd-schema-bundle/wiki)

</div>
