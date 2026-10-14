# WordPress Projects

A branch-based portfolio of WordPress engineering work. Each project branch contains its own implementation and evidence-backed documentation; the default branch remains a lightweight directory rather than duplicating project source.

## Project branches

| Branch | Technical focus | Source |
|---|---|---|
| [`brightpath-gutenburg`](https://github.com/Longspring2k/Wordpress-Projects/tree/brightpath-gutenburg) | A WordPress Full Site Editing block theme with reusable templates and patterns, a server-rendered form block, custom content models, editor/front-end parity tooling, responsive navigation, an icon system, and portable starter-content import/export. | Inspected at commit `4f65672527a99c008af144dd4cfb77d26f80d97f` |

## How this repository is organized

```text
main
└── README.md                    # Project directory

brightpath-gutenburg
├── theme.json                   # Design tokens and global block settings
├── templates/ and parts/        # Full Site Editing templates
├── patterns/                    # Reusable section compositions
├── blocks/form/                 # Dynamic form block
├── inc/                         # Theme capabilities and WordPress hooks
├── assets/                      # Front-end and editor CSS/JavaScript
└── starter/                     # Portable site-content snapshot
```

## What the documented branch demonstrates

- Native WordPress block-theme composition rather than a page-builder export.
- A design system shared across front-end templates and the editing canvas.
- WordPress-native content modeling, dynamic rendering, administration, and REST integration.
- Operational concerns including mail configuration, protected submissions, retention, and portable starter data.

## Review notes

- Open a project branch to inspect its complete file tree and implementation notes.
- Branch documentation describes repository evidence, not a claim about a currently deployed production site.
- Runtime installation and integration checks require a compatible WordPress environment and are documented within the project branch.

## License

Project-specific license information is retained in each branch. The documented theme branch declares `GPL-2.0-or-later` in its WordPress theme metadata.
