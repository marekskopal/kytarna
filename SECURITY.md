# Security Policy

## Supported versions

Ukolio follows semantic versioning. Security fixes are applied to the latest
released minor version. Please make sure you are running the most recent
release before reporting an issue.

| Version | Supported          |
| ------- | ------------------ |
| 1.0.x   | :white_check_mark: |
| < 1.0   | :x:                |

## Reporting a vulnerability

**Please do not open a public GitHub issue for security problems.**

Instead, open a
[private security advisory](https://github.com/marekskopal/ukolio/security/advisories/new)
on GitHub. Include:

- a description of the vulnerability and its impact,
- the steps or a proof-of-concept needed to reproduce it,
- the affected version / commit, and
- any suggested remediation if you have one.

We aim to acknowledge reports within a few days and will coordinate a fix and a
responsible-disclosure timeline with you. Once a fix is released we are happy to
credit reporters who wish to be named.

## Scope

This policy covers the open-source components in this repository: the backend
API and MCP server (`backend/`), the frontend (`frontend/`), and the reverse
proxy (`proxy/`). Issues in third-party dependencies should be reported upstream,
though we appreciate a heads-up if they affect Ukolio directly.
