<?php
/**
 * Empty WordPress AI client SDK stubs for tests that evaluate the guarded
 * provider bundle in includes/class-cloud-wordpress-ai-connector.php.
 *
 * @package NpcinkCloudAddon
 */

declare(strict_types=1);

namespace WordPress\AiClient\Providers {
	abstract class AbstractProvider {}
}

namespace WordPress\AiClient\Providers\Contracts {
	interface ProviderAvailabilityInterface {}
	interface ModelMetadataDirectoryInterface {}
}

namespace WordPress\AiClient\Providers\Models\Contracts {
	interface ModelInterface {}
}

namespace WordPress\AiClient\Providers\Models\TextGeneration\Contracts {
	interface TextGenerationModelInterface {}
}

namespace WordPress\AiClient\Providers\Models\ImageGeneration\Contracts {
	interface ImageGenerationModelInterface {}
}
