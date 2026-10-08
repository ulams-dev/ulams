<?php

namespace EscolaLms\CoursesImportExport\Enums;

use EscolaLms\Core\Enums\BasicEnum;

class CoursesImportExportPermissionsEnum extends BasicEnum
{
    public const COURSES_IMPORT = 'course-import-export_import';
    public const COURSES_EXPORT = 'course-import-export_export';
    public const COURSES_EXPORT_OWNED = 'course-import-export_export_authored';
    public const COURSES_CLONE = 'course-import-export_clone';
}
