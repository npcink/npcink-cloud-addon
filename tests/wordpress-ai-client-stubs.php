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

namespace WordPress\AiClient\Messages\Enums {
	final class MessageRoleEnum {
		public static function user(): object {
			return new class {
				public function isUser(): bool {
					return true;
				}
			};
		}

		public static function model(): object {
			return new class {
				public function isUser(): bool {
					return false;
				}
			};
		}
	}
}

namespace WordPress\AiClient\Messages\DTO {
	final class Message {
		private $role;
		private array $parts;

		public function __construct( $role, array $parts ) {
			$this->role  = $role;
			$this->parts = $parts;
		}

		public function getRole() {
			return $this->role;
		}

		public function getParts(): array {
			return $this->parts;
		}
	}

	final class MessagePart {
		private $file;
		private ?string $text;

		public function __construct( $value ) {
			$this->file = is_object( $value ) && method_exists( $value, 'getBase64Data' ) ? $value : null;
			$this->text = is_string( $value ) ? $value : null;
		}

		public function getFile() {
			return $this->file;
		}

		public function getText(): ?string {
			return $this->text;
		}
	}
}

namespace WordPress\AiClient\Files\DTO {
	final class File {
		private string $data;
		private string $mime_type;

		public function __construct( string $data, string $mime_type ) {
			$this->data      = $data;
			$this->mime_type = $mime_type;
		}

		public function getBase64Data(): ?string {
			return str_starts_with( $this->data, 'https://' ) ? null : $this->data;
		}

		public function getMimeType(): string {
			return $this->mime_type;
		}
	}
}

namespace WordPress\AiClient\Results\Enums {
	final class FinishReasonEnum {
		public static function stop(): string {
			return 'stop';
		}
	}
}

namespace WordPress\AiClient\Results\DTO {
	final class Candidate {
		public function __construct( public $message, public $finish_reason ) {}
	}

	final class TokenUsage {
		public function __construct( public int $input_tokens, public int $output_tokens, public int $total_tokens ) {}
	}

	final class GenerativeAiResult {
		public function __construct(
			public string $id,
			public array $candidates,
			public $usage,
			public $provider_metadata,
			public $model_metadata,
			public array $metadata = array()
		) {}
	}
}
