<?php

namespace Database\Factories;

use App\Models\Agreement;
use App\Models\AgreementAttachment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AgreementAttachment>
 *
 * AgreementAttachment::deleting calls PrivateFileService::deleteIfExists();
 * fabricated file paths are harmless.
 */
class AgreementAttachmentFactory extends Factory
{
    protected $model = AgreementAttachment::class;

    public function definition(): array
    {
        $filename = fake()->word().'.pdf';

        return [
            'agreement_id' => Agreement::factory(),
            'filename' => $filename,
            'file_path' => 'agreements/'.fake()->uuid().'/'.$filename,
            'mime_type' => fake()->optional()->mimeType(),
            'file_size' => fake()->optional()->numberBetween(1024, 1048576),
        ];
    }
}
