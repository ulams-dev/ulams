<?php

namespace EscolaLms\Recommender\Models;

use EscolaLms\Recommender\Database\Factories\MeetRecordingScreenFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MeetRecordingScreen extends Model
{
    use HasFactory;

    protected $fillable = [
        'id',
        'model_type',
        'model_id',
        'term',
        'file_path',
        'file_timestamp',
        'meet_recording_id',
    ];

    protected $casts = [
        'term' => 'datetime',
        'file_timestamp' => 'datetime',
    ];

    protected static function newFactory(): MeetRecordingScreenFactory
    {
        return MeetRecordingScreenFactory::new();
    }
}
