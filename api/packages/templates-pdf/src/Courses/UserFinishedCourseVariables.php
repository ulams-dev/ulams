<?php

namespace Ulams\TemplatesPdf\Courses;

use Illuminate\Support\Facades\Lang;
use Ulams\TemplatesPdf\Pdfme\CertificateTemplates;

class UserFinishedCourseVariables extends CommonUserAndCourseVariables
{
    public static function defaultSectionsContent(): array
    {
        return [
            'title' => Lang::get('Certificate for :course', ['course' => self::VAR_COURSE_TITLE]),
            // A4 landscape pdfme certificate (resources/pdfme/certificate-default.json)
            'content' => CertificateTemplates::content(CertificateTemplates::DEFAULT),
        ];
    }
}
