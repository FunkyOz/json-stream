# Development Guide

## Docker Development Environment

This project uses Docker Compose for development to ensure consistency across environments.

### Prerequisites

- Docker
- Docker Compose

### Environment Setup

1. **Copy environment file:**
   ```bash
   cp .env.example .env
   ```

2. **Configure environment variables** (optional):
   Edit `.env` to customize:
   - `PHP_VERSION` - PHP version (default: 8.0, supports 8.0-8.5)
   - `COMPOSER_VERSION` - Composer version (default: 2.9.2)
   - `USER_ID` - Your user ID for file permissions (default: 1000)
   - `GROUP_ID` - Your group ID for file permissions (default: 1000)
   - `TIMEZONE` - Timezone (default: UTC)

3. **Build and start containers:**
   ```bash
   docker compose up -d --build
   ```

4. **Verify container is running:**
   ```bash
   docker compose ps
   ```

### Common Commands

All development commands must be run inside the Docker container using:

```bash
docker compose exec php <command>
```

#### Composer Commands

```bash
# Install dependencies
docker compose exec php composer install

# Update dependencies
docker compose exec php composer update

# Dump autoload
docker compose exec php composer dump-autoload
```

#### Testing Commands

```bash
# Run all tests
docker compose exec php composer tests

# Run specific test suites
docker compose exec php composer tests:unit
docker compose exec php composer tests:types
docker compose exec php composer tests:lint
docker compose exec php composer tests:type-coverage
docker compose exec php composer tests:typos
docker compose exec php composer tests:refactor
```

#### Code Quality Commands

```bash
# Fix code style
docker compose exec php composer lint

# Apply refactoring suggestions
docker compose exec php composer refactor
```

#### Shell Access

Enter the container for interactive development:

```bash
docker compose exec php bash
```

Once inside, you can run commands directly:

```bash
composer install
pest
phpstan analyse
```

### Container Management

```bash
# Start containers
docker compose up -d

# Stop containers
docker compose down

# Restart containers
docker compose restart

# View logs
docker compose logs -f php

# Rebuild containers (after Dockerfile changes)
docker compose up -d --build
```

### File Permissions

The Docker container runs as a non-root user with the same UID/GID as specified in `.env` to avoid permission issues with mounted volumes.

### Volumes

The setup includes persistent volumes for:
- **composer-cache** - Composer package cache
- **bash-history** - Bash command history

### Network

Containers run on a custom bridge network `php-dev-network` with subnet `172.20.0.0/16`.

## Project Structure

```
json-stream-php/
├── src/
│   ├── Reader/          # JSON reading components
│   ├── Exception/       # Exception classes
│   └── Internal/        # Internal utilities (Buffer, Lexer, Parser)
├── tests/
│   ├── Unit/           # Unit tests
│   ├── Integration/    # Integration tests
│   └── Performance/    # Performance benchmarks
├── tasks/              # Task breakdown for development
└── docs/               # Documentation
```

**Note**: Writer functionality has been moved to the `feature/writer` branch for future development. The main branch focuses on reading capabilities for v1.0.

## Development Workflow

1. **Task-driven development**: Follow the tasks in `tasks/` directory
2. **Test-driven development**: Write tests before implementation
3. **Code quality**: Run linting and type checks before committing
4. **Documentation**: Update docs as you build features

## Quality Standards

- **100% code coverage** required for unit tests
- **100% type coverage** required (all parameters and return types)
- **PSR-12** coding standards enforced by Laravel Pint
- **PHPStan** level max for static analysis
- **No typos** in code or documentation
- **Rector** suggestions must be addressed
