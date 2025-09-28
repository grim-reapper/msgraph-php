# Microsoft Graph PHP Package

[![Latest Version](https://img.shields.io/packagist/v/grim-reapper/msgraph-php.svg)](https://packagist.org/packages/grim-reapper/msgraph-php)
[![PHP Version](https://img.shields.io/packagist/php-v/grim-reapper/msgraph-php.svg)](https://packagist.org/packages/grim-reapper/msgraph-php)
[![Build Status](https://img.shields.io/github/actions/workflow/status/grim-reapper/msgraph-php/ci.yml)](https://github.com/grim-reapper/msgraph-php/actions)
[![Code Coverage](https://img.shields.io/codecov/c/github/grim-reapper/msgraph-php)](https://codecov.io/gh/grim-reapper/msgraph-php)
[![License](https://img.shields.io/packagist/l/grim-reapper/msgraph-php.svg)](https://packagist.org/packages/grim-reapper/msgraph-php)

A comprehensive and robust PHP package for integrating with Microsoft Graph API, providing seamless access to Microsoft 365 services including OneDrive, Outlook, Calendar, Teams, and more.

## Features

- 🚀 **OAuth 2.0 Authentication** - Secure authentication with Azure Active Directory
- 📁 **OneDrive Integration** - Complete file management capabilities
- 📄 **Document Conversion** - Convert documents (DOCX to PDF, etc.)
- 📧 **Email Operations** - Send, read, and manage emails
- 📅 **Calendar Management** - Create and manage events
- 👥 **User Management** - User profiles and presence
- 🔒 **Enterprise Security** - Production-ready security practices
- 🧪 **Comprehensive Testing** - Unit and integration tests
- 📚 **Rich Documentation** - Complete API documentation and examples

## Requirements

- PHP 8.1 or higher
- Composer for dependency management
- Azure AD application for authentication

## Installation

```bash
composer require grim-reapper/msgraph-php
```

## Quick Start

### 1. Authentication Setup

First, create an Azure AD application and obtain your credentials:

```php
use GrimReapper\MsGraph\Authentication\AuthConfig;
use GrimReapper\MsGraph\Core\GraphClient;

$config = new AuthConfig([
    'clientId' => 'your-client-id',
    'clientSecret' => 'your-client-secret',
    'tenantId' => 'your-tenant-id',
    'redirectUri' => 'https://yourapp.com/callback'
]);

$graphClient = new GraphClient($config);
```

### 2. OneDrive Operations

```php
use GrimReapper\MsGraph\Services\OneDriveService;

$oneDrive = new OneDriveService($graphClient);

// List files
$files = $oneDrive->listFiles('/');

// Upload a file
$oneDrive->uploadFile('/path/to/local/file.pdf', 'Documents/file.pdf');

// Download a file
$content = $oneDrive->downloadFile('/Documents/file.pdf');
```

### 3. Document Conversion

```php
use GrimReapper\MsGraph\Services\DocumentService;

$documentService = new DocumentService($graphClient);

// Convert DOCX to PDF
$convertedContent = $documentService->convertDocument(
    '/Documents/document.docx',
    'pdf'
);
```

### 4. Email Operations

```php
use GrimReapper\MsGraph\Services\MailService;

$mailService = new MailService($graphClient);

// Send email
$mailService->sendEmail([
    'to' => 'recipient@example.com',
    'subject' => 'Test Email',
    'body' => 'This is a test email from Microsoft Graph PHP'
]);

// Read emails
$messages = $mailService->getMessages();
```

## Documentation

- [API Documentation](docs/api/) - Complete API reference
- [Authentication Guide](docs/guides/authentication.md) - Setup OAuth 2.0
- [Examples](docs/examples/) - Code examples and use cases
- [Contributing](CONTRIBUTING.md) - Development guidelines

## Configuration

### Authentication Options

```php
$config = new AuthConfig([
    'clientId' => 'your-client-id',
    'clientSecret' => 'your-client-secret',
    'tenantId' => 'your-tenant-id',
    'redirectUri' => 'https://yourapp.com/callback',
    'scopes' => ['https://graph.microsoft.com/.default'],
    'cache' => new FileCache(), // Optional: Custom cache implementation
    'logger' => new Logger(), // Optional: Custom logger
]);
```

### Advanced Configuration

```php
// Custom HTTP client
$httpClient = new GuzzleHttp\Client([
    'timeout' => 30,
    'headers' => ['User-Agent' => 'MyApp/1.0']
]);

$graphClient = new GraphClient($config, $httpClient);
```

## Services Overview

| Service | Description | Key Methods |
|---------|-------------|-------------|
| `OneDriveService` | File management | `uploadFile()`, `downloadFile()`, `listFiles()`, `deleteFile()` |
| `DocumentService` | Document conversion | `convertDocument()`, `getSupportedFormats()` |
| `MailService` | Email operations | `sendEmail()`, `getMessages()`, `getMessage()` |
| `CalendarService` | Calendar management | `createEvent()`, `getEvents()`, `updateEvent()` |
| `UserService` | User management | `getUser()`, `getUsers()`, `getUserPhoto()` |

## Error Handling

```php
use GrimReapper\MsGraph\Exceptions\GraphException;
use GrimReapper\MsGraph\Exceptions\AuthenticationException;

try {
    $files = $oneDrive->listFiles('/');
} catch (AuthenticationException $e) {
    // Handle authentication errors
    echo "Authentication failed: " . $e->getMessage();
} catch (GraphException $e) {
    // Handle API errors
    echo "Graph API error: " . $e->getMessage();
} catch (Exception $e) {
    // Handle other errors
    echo "General error: " . $e->getMessage();
}
```

## Testing

```bash
# Run all tests
composer test

# Run with coverage
composer test:coverage

# Run only unit tests
composer test -- --testsuite "Unit Tests"

# Run only integration tests
composer test -- --testsuite "Integration Tests"
```

## Security

This package implements security best practices:

- Secure token storage and refresh
- Input validation and sanitization
- Protection against common vulnerabilities
- HTTPS enforcement
- Rate limiting support

## Contributing

We welcome contributions! Please see [CONTRIBUTING.md](CONTRIBUTING.md) for guidelines.

1. Fork the repository
2. Create your feature branch (`git checkout -b feature/amazing-feature`)
3. Run tests (`composer test`)
4. Commit your changes (`git commit -m 'Add amazing feature'`)
5. Push to the branch (`git push origin feature/amazing-feature`)
6. Open a Pull Request

## License

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.

## Support

- 📖 [Documentation](docs/)
- 🐛 [Issues](https://github.com/grim-reapper/msgraph-php/issues)
- 💬 [Discussions](https://github.com/grim-reapper/msgraph-php/discussions)
- 📧 [Email Support](mailto:support@grim-reapper.com)

## Changelog

See [CHANGELOG.md](CHANGELOG.md) for a list of changes and version history.

---

Made with ❤️ by [Grim Reapper Corp](https://grim-reapper.com)
