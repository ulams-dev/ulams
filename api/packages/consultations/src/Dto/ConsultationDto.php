<?php

namespace EscolaLms\Consultations\Dto;

use Carbon\Carbon;
use EscolaLms\Consultations\Dto\Contracts\ModelDtoContract;
use EscolaLms\Consultations\Enum\ConstantEnum;
use EscolaLms\Consultations\Models\Consultation;
use EscolaLms\Consultations\Models\ConsultationProposedTerm;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;

class ConsultationDto extends BaseDto implements ModelDtoContract
{
    protected string $name;
    protected string $status;
    protected string $description;
    protected ?string $shortDesc = null;
    protected ?string $activeTo = null;
    protected ?string $activeFrom = null;
    protected ?string $duration = null;
    protected ?int $authorId = null;
    protected $imagePath = null;
    protected $logotypePath = null;
    protected ?bool $analyzeEnabled;

    public function model(): Consultation
    {
        // @phpstan-ignore-next-line
        return Consultation::newModelInstance();
    }

    public function toArray($filters = false): array
    {
        $result = $this->fillInArray($this->model()->getFillable());
        return $filters ? array_filter($result) : $result;
    }

    public function getImagePath()
    {
        if ($this->imagePath !== null) {
            return $this->imagePath === null ? '' : Str::after($this->imagePath, Str::after(env('AWS_URL'), 'https://') . '/');
        }
        return null;
    }

    public function getLogotypePath()
    {
        if ($this->logotypePath !== null) {
            if ($this->logotypePath) {
                $logotypePath = Str::after($this->logotypePath, Str::after(env('AWS_URL'), 'https://') . '/');
                return Str::startsWith($logotypePath, ConstantEnum::DIRECTORY) ? $logotypePath : ConstantEnum::DIRECTORY . '/' .$logotypePath;
            }
            return '';
        }
        return null;
    }

    protected function setProposedTerms(array $proposedTerms): void
    {
        $result = [];
        foreach ($proposedTerms as $term) {
            if (is_int($term)) {
                // @phpstan-ignore-next-line
                $date = Carbon::parse($term / 1000);
            } else {
                $date = Carbon::parse($term);
            }
            $result[] = new ConsultationProposedTerm(['proposed_at' => $date]);
        }
        $this->relations['proposedTerms'] = $result;
    }

    protected function setCategories(array $categories): void
    {
        $this->relations['categories'] = $categories;
    }

    protected function setImage($file): void
    {
        $this->files['image_path'] = $file;
    }

    protected function setLogotype($logotype): void
    {
        $this->files['logotype_path'] = $logotype;
    }

    protected function setActiveTo(?string $activeTo): void
    {
        $this->activeTo = $activeTo ? Carbon::make($activeTo) : null;
    }

    protected function setActiveFrom(?string $activeFrom): void
    {
        $this->activeFrom = $activeFrom ? Carbon::make($activeFrom) : null;
    }

    protected function setTeachers(array $teachers): void
    {
        $this->relations['teachers'] = $teachers;
    }

    public function setAnalyzeEnabled(bool $analyzeEnabled): void
    {
        $this->analyzeEnabled = $analyzeEnabled;
    }
}
