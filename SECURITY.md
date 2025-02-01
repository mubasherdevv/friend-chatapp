# Security Policy

## Supported Versions

| Version | Supported          |
| ------- | ------------------ |
| 1.0.x   | :white_check_mark: |

## Reporting a Vulnerability

If you discover a security vulnerability within this chat application, please send an email to [freeg5334@gmail.com]. All security vulnerabilities will be promptly addressed.

## VEX Statement

This project uses the following dependencies and has been assessed for vulnerabilities:

### Known Issues and Mitigations

1. **PHP Dependencies**
   - All PHP dependencies are managed through secure connections
   - No known exploitable vulnerabilities in current production code
   - Regular security updates are applied

2. **Frontend Dependencies**
   - TailwindCSS (CDN): No direct vulnerabilities as it's CSS only
   - Other frontend libraries are loaded through secure CDNs

3. **File Upload Security**
   - Strict file type validation
   - Size limitations enforced
   - Secure file storage implementation

4. **Database Security**
   - Prepared statements used throughout
   - Input validation implemented
   - Secure connection settings

### Security Measures

1. **Authentication**
   - Session-based authentication
   - Password hashing implemented
   - CSRF protection

2. **Data Protection**
   - XSS prevention
   - SQL injection protection
   - Input sanitization

## Updates

This security policy will be updated as new versions are released or when security measures are enhanced.
