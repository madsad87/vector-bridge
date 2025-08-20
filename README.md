# Vector Bridge – MVDB External Indexer

A WordPress plugin that provides an admin-only interface for ingesting external content into WP Engine's Managed Vector Database (MVDB).

## Current Status

**✅ Working Features:**
- URL/Sitemap processing with content extraction
- Intelligent content chunking with configurable overlap
- MVDB integration via GraphQL API
- Connection validation and dry run testing
- Admin interface with settings management

**🚧 Under Development:**
- File upload processing (PDF, DOCX, TXT, MD)
- Content browser with document management
- Background job queue system

## Features

- **URL Processing**: Extract content from web pages and sitemaps with robots.txt compliance
- **Intelligent Chunking**: Split content into manageable chunks with configurable overlap
- **MVDB Integration**: Direct integration with WP Engine's Managed Vector Database via GraphQL
- **Dry Run Mode**: Preview chunking results with sample fixtures without making MVDB calls
- **Connection Validation**: Test MVDB connectivity before processing
- **Admin-Only Interface**: Secure access restricted to users with `manage_options` capability

## Requirements

- **PHP**: 8.1 or higher
- **WordPress**: 6.5 or higher
- **WP Engine Managed Vector Database**: Active MVDB instance with GraphQL endpoint

## Installation

1. Upload the plugin folder to `/wp-content/plugins/`
2. Run `composer install` in the plugin directory to install dependencies
3. Activate the plugin through the WordPress admin
4. Configure your MVDB settings under **Vector Bridge > Settings**

## Configuration

### MVDB Connection

- **MVDB Endpoint URL**: Your GraphQL endpoint URL
- **MVDB Token**: Authentication token for your MVDB instance

### Content Processing

- **Default Collection**: Default collection name for indexed content
- **Tenant**: Optional tenant identifier for multi-tenant setups
- **Chunk Size**: Target size for content chunks in tokens (100-5000)
- **Overlap Percentage**: Percentage of overlap between adjacent chunks (0-50%)

### Performance Settings

- **Batch Size**: Number of chunks to process in each batch (1-1000)
- **QPS**: Maximum queries per second to MVDB (0.1-100)

## Usage

### Processing URLs

1. Navigate to **Vector Bridge** in the WordPress admin
2. Enter a URL in the "URL Processing" section
3. Click "Process URL"

The plugin will:
- Check robots.txt compliance
- Extract and clean content using readability algorithms
- Split content into chunks with configured overlap
- Index each chunk into MVDB immediately

### Testing Configuration

Use the "Dry Run with Fixtures" button to:
- Test chunking configuration with sample content
- Preview how content will be split
- Verify settings without making MVDB calls

### Connection Validation

Use the "Validate Connection" button to:
- Test MVDB endpoint connectivity
- Verify authentication token
- Check GraphQL schema availability

## Processing Method

The plugin currently processes URLs **synchronously** - one URL at a time without background queuing. This approach:

- ✅ Provides immediate feedback
- ✅ Simplifies error handling
- ✅ Works reliably for single URL processing
- ⚠️ May timeout on very large pages
- ⚠️ Not suitable for bulk processing

Future versions will include background job processing for handling multiple URLs and large file uploads.

## Document IDs

The plugin generates stable document IDs using the format:
```
vb_{sha256_hash_of_source_collection_chunk_index}
```

This ensures:
- Consistent IDs for the same content
- No duplicates when re-processing
- Easy identification of document sources

## Metadata

Each indexed document includes metadata:

```json
{
  "post_title": "Extracted title or content preview",
  "post_content": "Chunked content text",
  "post_type": "collection_name",
  "post_status": "publish",
  "post_date": "2024-01-01T12:00:00+00:00",
  "chunk_index": 0,
  "source_origin": "source_url_or_filename",
  "indexed_by": "vector-bridge",
  "wordpress_site": "https://example.com",
  "tenant": "optional_tenant_id"
}
```

## Security

- **Admin-Only Access**: Requires `manage_options` capability
- **Nonce Protection**: All AJAX requests use WordPress nonces
- **Token Masking**: Sensitive data masked in UI and logs
- **Input Sanitization**: All user inputs properly sanitized
- **Robots.txt Compliance**: Respects website crawling policies

## Dependencies

- **guzzlehttp/guzzle**: HTTP client for API calls
- **league/html-to-markdown**: HTML content cleaning
- **smalot/pdfparser**: PDF text extraction (for future file upload feature)
- **phpoffice/phpword**: DOCX content extraction (for future file upload feature)

## Build & Deploy

### Local Development Build

To create a distributable ZIP file for WP Engine deployment:

```bash
# Build the plugin package
npm run package
```

This creates `build/vector-bridge-mvdb-indexer.zip` containing:
- All plugin source code
- Vendor dependencies (pre-installed)
- Production-ready assets
- Documentation files

### Installation via WordPress Admin

1. Upload the ZIP file via **WordPress Admin > Plugins > Add New > Upload Plugin**
2. Activate the plugin
3. Configure MVDB settings under **Vector Bridge > Settings**

**Important**: WP Engine doesn't require Composer - the vendor directory is already included in the ZIP file.

### Automated Builds

The plugin includes GitHub Actions workflow that automatically:
- Builds the plugin on tag pushes (`v*`)
- Installs PHP dependencies with Composer
- Creates optimized ZIP packages
- Uploads artifacts and creates GitHub releases

### Manual Build Process

If you need to build manually without npm:

```bash
# Install PHP dependencies
composer install --no-dev --optimize-autoloader

# Create build directory
mkdir -p build

# Run the packaging script
bash scripts/package.sh
```

## Development

### Directory Structure

```
vector-bridge-mvdb-indexer/
├── src/
│   ├── Core/
│   │   └── Plugin.php
│   ├── Admin/
│   │   ├── AdminMenu.php
│   │   └── Settings.php
│   └── Services/
│       ├── MVDBService.php
│       ├── ChunkingService.php
│       └── ExtractionService.php
├── assets/
│   ├── css/
│   │   └── admin.css
│   └── js/
│       └── admin.js
├── scripts/
│   └── package.sh
├── .github/
│   └── workflows/
│       └── package.yml
├── composer.json
├── package.json
└── vector-bridge-mvdb-indexer.php
```

### Building

```bash
# Install PHP dependencies
composer install

# Install Node.js dependencies (optional)
npm install

# Build assets (optional)
npm run build

# Create distributable package
npm run package
```

### Testing

```bash
# Run PHP tests
composer test

# Run code standards check
composer cs

# Fix code standards
composer cbf
```

## Troubleshooting

### Common Issues

1. **Connection Failed**: Check MVDB endpoint URL and token
2. **No Text Content**: Ensure URLs contain extractable text content
3. **Processing Timeout**: Try smaller pages or increase PHP max_execution_time
4. **Rate Limiting**: Reduce QPS setting if getting throttled
5. **Date Format Errors**: Plugin uses ISO 8601 format for MVDB compatibility

### Debug Information

Enable WordPress debug logging and check:
- PHP error logs
- WordPress debug.log
- Browser developer console for AJAX errors

### Support

For issues related to:
- **Plugin functionality**: Check WordPress admin notices and logs
- **MVDB connectivity**: Verify endpoint and authentication
- **URL processing**: Ensure URLs are accessible and contain text content
- **Performance**: Adjust chunk size and QPS settings

## Planned Features

### File Upload Processing
- PDF text extraction
- DOCX content extraction
- TXT and MD file support
- Drag-and-drop interface

### Content Browser
- Document search and filtering
- Vector similarity search testing
- Document management (view, delete)
- Bulk operations

### Background Processing
- WordPress Cron integration for large jobs
- Bulk URL processing
- Progress tracking and notifications
- Job retry mechanisms

### Enhanced Analytics
- Processing statistics
- Performance metrics
- Usage reporting
- Error tracking

## Changelog

### 1.0.0
- Initial release
- URL processing with content extraction
- Intelligent content chunking
- MVDB GraphQL integration
- Dry run mode with sample fixtures
- Connection validation
- Admin interface with settings
- Fixed date format compatibility with MVDB
- Professional admin styling with "under construction" indicators

## License

GPL v2 or later

## Author

Madison Sadler
