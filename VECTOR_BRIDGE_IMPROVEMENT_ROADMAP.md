# Vector Bridge MVDB Indexer - Improvement Roadmap

## Overview
This roadmap outlines the enhancement of the Vector Bridge plugin to support multiple content types with custom data schemas, focusing on video/VTT processing while maintaining the existing WordPress post functionality.

## Current State Analysis

### Existing Architecture
- **Plugin Structure**: Singleton pattern with service registration
- **Services**: MVDBService, ChunkingService, ExtractionService, Settings
- **Content Flow**: Extract → Chunk → Index to MVDB
- **Supported Types**: URLs, PDF, DOCX, TXT, MD files
- **Data Schema**: WordPress-centric (post_title, post_content, post_type, etc.)

### Current Data Structure
```json
{
  "data": {
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
}
```

## Proposed Enhancements

### 1. Content Type Profiles System

#### Unified Base Schema
All content types will share these core fields:
```json
{
  "post_type": "video|document|webpage|post",
  "url_source": "https://example.com/content-page",
  "chunk_index": 0,
  "indexed_by": "vector-bridge",
  "wordpress_site": "https://yoursite.com",
  "tenant": "optional_tenant_id"
}
```

#### Type-Specific Extensions

**Video Profile:**
```json
{
  "video_title": "Video Title",
  "transcript_content": "VTT chunked text",
  "video_cue": "00:00:01.000 --> 00:00:04.000",
  "post_type": "video",
  "url_source": "https://youtube.com/watch?v=xyz",
  "duration": 3600,
  "speaker": "Speaker Name",
  "video_file_url": "https://example.com/video.mp4"
}
```

**Document Profile:**
```json
{
  "document_title": "Document Title", 
  "document_content": "Extracted text content",
  "post_type": "document",
  "url_source": "https://example.com/document-page",
  "file_size": 1024000,
  "page_count": 25,
  "author": "Document Author",
  "creation_date": "2024-01-01"
}
```

**Webpage Profile:**
```json
{
  "post_title": "Page Title",
  "post_content": "Extracted content", 
  "post_type": "webpage",
  "url_source": "https://example.com/page",
  "meta_description": "Page description",
  "publish_date": "2024-01-01"
}
```

### 2. WordPress Admin UX Enhancement

#### Current Interface
- Dashboard with MVDB Connection Status
- Quick Actions: Test Configuration, Process URL, Upload File
- Recent Jobs section

#### Proposed Tabbed Interface
Replace current Quick Actions with tabbed interface:

**Tab 1: URL**
- Current "Process URL" functionality
- Extract and index content from web pages/sitemaps

**Tab 2: File** 
- Current "Upload File" for documents
- Support: PDF, DOCX, TXT, MD files

**Tab 3: Video** (NEW)
```
Video URL: [________________] (YouTube, Vimeo, Google Drive, etc.)
VTT File:  [Choose File] [No file chosen]
Collection: [dropdown or text field]
Optional Fields:
  Title: [________________]
  Speaker: [________________]
  Description: [________________]
[Process Video & VTT] button
```

**Tab 4: Bulk** (FUTURE)
- Bulk processing capabilities
- CSV upload for multiple items

## Implementation Plan

### Phase 1: Core Content Type System

#### 1.1 Content Type Factory Pattern
**File**: `src/Services/ContentTypeFactory.php`
```php
class ContentTypeFactory {
    public static function createDataBuilder(string $content_type): ContentTypeBuilderInterface
    public static function createExtractor(string $content_type): ExtractionServiceInterface  
    public static function createChunker(string $content_type): ChunkingServiceInterface
}
```

#### 1.2 Enhanced MVDBService
**Modify**: `src/Services/MVDBService.php`
- Update `indexBatch()` method to use content type factory
- Add `buildDocumentData()` method with type-specific logic
```php
private function buildDocumentData(array $chunk, string $collection, string $content_type): array {
    $base_data = [
        'post_type' => $content_type,
        'url_source' => $chunk['url_source'],
        'chunk_index' => $chunk['chunk_index'],
        'indexed_by' => 'vector-bridge'
    ];
    
    return match($content_type) {
        'video' => array_merge($base_data, $this->buildVideoData($chunk)),
        'document' => array_merge($base_data, $this->buildDocumentData($chunk)),
        'webpage' => array_merge($base_data, $this->buildWebpageData($chunk)),
        default => array_merge($base_data, $this->buildDefaultData($chunk))
    };
}
```

#### 1.3 Content Type Builders
**New Files**:
- `src/Services/ContentTypes/VideoContentBuilder.php`
- `src/Services/ContentTypes/DocumentContentBuilder.php`
- `src/Services/ContentTypes/WebpageContentBuilder.php`

### Phase 2: Video/VTT Processing

#### 2.1 VTT Extraction Service
**New File**: `src/Services/VttExtractionService.php`
```php
class VttExtractionService {
    public function parseVttFile(string $file_path): array
    public function extractTimestamps(string $vtt_content): array
    public function chunkByTimeSegments(array $vtt_segments): array
    public function validateVttFormat(string $content): bool
}
```

#### 2.2 Enhanced ExtractionService
**Modify**: `src/Services/ExtractionService.php`
- Add VTT file type to `SUPPORTED_TYPES`
- Add `extractFromVtt()` method
- Add video URL processing capabilities

#### 2.3 Video-Specific Chunking
**Modify**: `src/Services/ChunkingService.php`
- Add `chunkVideoContent()` method for time-based chunking
- Preserve timestamp information in chunks

### Phase 3: Admin Interface Enhancement

#### 3.1 Tabbed Interface
**Modify**: `assets/js/admin.js`
- Implement tab switching functionality
- Add video-specific form handling
- Update AJAX calls for new content types

**Modify**: `assets/css/admin.css`
- Add tab styling
- Video form layout styles

#### 3.2 New AJAX Handlers
**Modify**: `src/Core/Plugin.php`
- Add `handleProcessVideo()` method
- Update existing handlers to support content types
- Add video file validation

#### 3.3 Admin Templates
**Modify**: Admin template files to include tabbed interface
- Update dashboard template
- Add video processing form
- Enhance file upload interface

### Phase 4: Enhanced Features

#### 4.1 Content Browser Updates
- Filter by content type
- Video-specific display (show timestamps, duration)
- Enhanced search with content-type filters

#### 4.2 Settings Enhancement
- Content type-specific settings
- Video processing preferences
- VTT parsing options

#### 4.3 Bulk Processing
- CSV upload for multiple videos
- Batch VTT processing
- Progress tracking for large jobs

## Technical Specifications

### VTT File Format Support
```
WEBVTT

00:00:01.000 --> 00:00:04.000
This is the first subtitle.

00:00:05.000 --> 00:00:08.000
This is the second subtitle.
```

### Video URL Support
- YouTube URLs (with manual VTT upload)
- Vimeo URLs (with manual VTT upload)
- Direct video file URLs
- Google Drive video links
- Any publicly accessible video URL

### Database Schema Considerations
- No database changes required (MVDB handles JSON flexibly)
- All new fields stored in existing MVDB document structure
- Backward compatibility maintained

### Security Considerations
- VTT file validation and sanitization
- Video URL validation
- File upload security (existing WordPress mechanisms)
- Content type validation

## Migration Strategy

### Backward Compatibility
- Existing indexed content remains unchanged
- Current workflows continue to function
- New content types are additive, not replacing

### Rollout Plan
1. **Phase 1**: Core content type system (no UI changes)
2. **Phase 2**: Video processing backend (no UI changes)
3. **Phase 3**: New tabbed UI (user-facing changes)
4. **Phase 4**: Enhanced features

### Testing Strategy
- Unit tests for new services
- Integration tests for content type processing
- UI testing for tabbed interface
- End-to-end testing with real VTT files

## Success Metrics

### Functional Goals
- [ ] Support VTT file processing with timestamp preservation
- [ ] Implement content type-specific data schemas
- [ ] Create intuitive tabbed admin interface
- [ ] Maintain backward compatibility
- [ ] Enable content type filtering in searches

### Performance Goals
- Video processing time < 30 seconds for typical VTT files
- No impact on existing URL/file processing performance
- Efficient chunking for time-based content

### User Experience Goals
- Clear separation of content types in UI
- Intuitive video processing workflow
- Helpful error messages and validation
- Consistent interface patterns

## Future Considerations

### Additional Content Types
- Audio files with transcript support
- Image collections with metadata
- Social media content
- API-based content sources

### Advanced Video Features
- Automatic transcript generation (via APIs)
- Speaker identification and separation
- Chapter/topic detection
- Video thumbnail extraction

### Integration Opportunities
- WordPress media library integration
- Third-party video platform APIs
- Automated content discovery
- AI-powered content enhancement

## Conclusion

This roadmap provides a comprehensive path to enhance Vector Bridge with multi-content-type support while maintaining its current functionality. The phased approach ensures minimal disruption to existing users while adding powerful new capabilities for video content indexing.

The architecture is designed to be extensible, allowing for easy addition of new content types in the future. The focus on user experience ensures that the enhanced functionality remains accessible and intuitive for WordPress administrators.
