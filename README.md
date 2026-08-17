# oscryn/framework

The core of the **Oscryn** framework — a tiny, modern PHP framework.

- Fluent routing, an ActiveRecord-style ORM with casts, a raw-SQL-free schema builder and migrations
- Latte templating, Ignition-style error pages, pretty JSON, CSRF + sessions, encryption
- Global helpers: `env()`, `db()`, `dd()`, `live_dump()`, `view()`, `encrypt()`, `csrf_field()` and more

## Install

```
composer require oscryn/framework
```

Or create a full project with the skeleton:

```
composer create-project oscryn/oscryn my-app
```
