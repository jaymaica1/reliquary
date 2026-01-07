# External Integrations

## OpenStreetMap Integration

The project uses OpenStreetMap's Nominatim API for geocoding:

- **OpenStreetMapService**: A service that provides methods for searching addresses
- Used for location autocomplete in relic forms
- Returns formatted results with coordinates and address details
- Respects OpenStreetMap's usage policy with proper User-Agent

## Image Management System

The project includes a comprehensive image management system:

- **ImageService**: A service that handles image uploads, storage, and deletion
- Supports different image types (RelicImage, UserImage)
- Uses a hashed directory structure to prevent filesystem issues with large numbers of files
- Handles file naming, moving, and cleanup
- Associates images with their uploaders for audit purposes

## AI Image Generation

The project integrates with AI providers to generate portraits for saints and other entities.

### AI Image Service

- **AiImageService**: Orchestrates image generation using different providers.
- Supports provider-specific configurations via `ConfigurationService`.

### Available Providers and Models

The following providers and models are available for image generation:

#### OpenAI (`openai`)
- **GPT Image 1.5** (`gpt-image-1.5`)
    - Low: $0.009 (1024x1024), $0.013 (other)
    - Medium: $0.034 (1024x1024), $0.05 (other)
    - High: $0.133 (1024x1024), $0.2 (other)
- **GPT Image Latest** (`gpt-image-latest`)
    - Low: $0.009 (1024x1024), $0.013 (other)
    - Medium: $0.034 (1024x1024), $0.05 (other)
    - High: $0.133 (1024x1024), $0.2 (other)
- **GPT Image 1** (`gpt-image-1`)
    - Low: $0.011 (1024x1024), $0.016 (other)
    - Medium: $0.042 (1024x1024), $0.063 (other)
    - High: $0.167 (1024x1024), $0.25 (other)
- **GPT Image 1 Mini** (`gpt-image-1-mini`)
    - Low: $0.005 (1024x1024), $0.006 (other)
    - Medium: $0.011 (1024x1024), $0.015 (other)
    - High: $0.036 (1024x1024), $0.052 (other)
- **DALL-E 3** (`dall-e-3`)
    - Standard: $0.04 (1024x1024), $0.08 (other)
    - HD: $0.08 (1024x1024), $0.12 (other)
- **DALL-E 2** (`dall-e-2`)
    - Standard: $0.016 (256x256), $0.018 (512x512), $0.02 (1024x1024)

#### Google Gemini (`gemini`)
- **Gemini 3 Pro Image Preview** (`gemini-3-pro-image-preview`): Native model optimized for speed and flexibility.
    - Input: ~$0.0011 per image ($2.00/1M tokens)
    - Output: ~$0.134 per 1K/2K image, ~$0.24 per 4K image ($120.00/1M tokens)
- **Gemini 2.5 Flash Image** (`gemini-2.5-flash-image`): Optimized for speed.
    - Output: $0.039 per image
- **Imagen 4**
    - **Fast** (`imagen-4.0-fast-generate-001`): $0.02 per image
    - **Standard** (`imagen-4.0-generate-001`): $0.04 per image
    - **Ultra** (`imagen-4.0-ultra-generate-001`): $0.06 per image
- **Imagen 3** (`imagen-3.0-generate-002`): $0.03 per image
- **Imagen 3.0** (`imagen-3.0-generate-001`): Legacy Imagen 3 model.
- **Imagen 3.0 Fast** (`imagen-3.0-fast-generate-001`): Faster version of legacy Imagen 3.0.