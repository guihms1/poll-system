# 🗳️ Custom Voting Platform – Drupal 10

This is a Drupal 10-based application designed for managing public polls. It includes features for creating, editing, and displaying polls, along with RESTful API access for integration purposes.

## Prerequisites

Ensure the following are installed on your system:

- [Docker](https://www.docker.com/)
- [Lando](https://lando.dev/), a containerized development environment

---

## Local Setup Instructions

### 1. Clone the repository

```bash
git clone git@github.com:guihms1/poll-system.git
cd poll-system
```

### 2. Boot up the environment

```bash
lando start
```

> This sets up all required services, including Apache, PHP, MySQL, and Drush.

### 3. Install Composer dependencies

```bash
lando composer install
```

### 4. Load the database

Ensure there's a database dump at `db/dump.sql.gz`. Then run:

```bash
lando db-import db/dump.sql.gz
```

### 5. Access the application

Once the containers are running and the database is loaded, access the site via:

```
http://poll-system.lndo.site/
```

---

## Admin Login

To log in as an admin user, generate a one-time login link with:

```bash
lando drush uli --uri=http://poll-system.lndo.site
```

Open the generated URL in your browser (preferably in incognito mode).

---

## Directory Overview

```
poll-system/
├── .lando.yml                   # Lando configuration for local dev
├── web/                         # Drupal root directory
├── db/dump.sql.gz               # Preconfigured database dump
├── composer.json                # PHP dependencies
├── modules/custom/poll_system/  # Custom functionality for poll management
└── README.md
```

---

## Running Tests

To execute unit tests for the poll module:

```bash
lando unit-test
```

> Test execution is defined within the `.lando.yml` and runs in the container environment.

---

## API Endpoints

### GET `/api/poll-system/{identifier}`

**Purpose:**  
Retrieve a poll by its machine-readable name. Returns poll metadata, options, and optionally the result.

**Requirements:**  
- User must have permission to vote
- Voting module must be active

**Sample Request:**

```bash
curl -X GET "http://poll-system.lndo.site:8000/api/poll-system/{do_you_have_pets}" \
  -u admin:admin \
  -H "Accept: application/json"
```

---

### POST `/api/poll-system/{identifier}/vote`

**Purpose:**  
Submit a response to a poll by specifying the selected `option_id`.

**Authentication:**  
Requires session cookie or token authentication.

**Sample Request:**

```bash
curl -X POST "http://poll-system.lndo.site:8000/api/poll-system/{identifier}/vote" \
  --cookie "SESSabcd=your-session-cookie" \
  -H "Content-Type: application/json" \
  -d '{"option_id": 1}'
```

> The endpoint expects authenticated users with voting privileges.

---

## Additional Information

- Activate the module if it's not yet enabled:
  ```bash
  lando drush en poll_system -y
  ```

- To change the domain used locally, edit the `proxy:` setting in your `.lando.yml`.
