/**
 * Curated example packages for the LMS demo seeder.
 *
 * Source: the same h5p.org export directory the old PHP seeder
 * (headless-h5p ContentLibrarySeeder) used. Checked 2026-10-08: some old
 * exports now return 404 (image-hotspots-2-825, interactive-video-2-618,
 * course-presentation-21-21180, the branching-scenario example). For those we
 * use the H5P Hub's content type package (api.h5p.org/v1/content-types/<lib>),
 * which ships the libraries plus a demo content object.
 */
export interface SamplePackage {
    key: string;
    contentType: string;
    machineName: string;
    url: string;
    source: 'h5p.org-export' | 'h5p-hub';
    note?: string;
}

const EXPORTS = 'https://h5p.org/sites/default/files/h5p/exports';
const HUB = 'https://api.h5p.org/v1/content-types';

export const SAMPLE_PACKAGES: SamplePackage[] = [
    {
        key: 'image-hotspots',
        contentType: 'Image Hotspots',
        machineName: 'H5P.ImageHotspots',
        url: `${HUB}/H5P.ImageHotspots`,
        source: 'h5p-hub',
        note: 'h5p.org export image-hotspots-2-825 is 404; hub demo has no background image'
    },
    {
        key: 'drag-and-drop',
        contentType: 'Drag and Drop',
        machineName: 'H5P.DragQuestion',
        url: `${EXPORTS}/drag-and-drop-712.h5p`,
        source: 'h5p.org-export'
    },
    {
        key: 'dialog-cards',
        contentType: 'Dialog Cards',
        machineName: 'H5P.Dialogcards',
        url: `${EXPORTS}/dialog-cards-620.h5p`,
        source: 'h5p.org-export'
    },
    {
        key: 'flashcards',
        contentType: 'Flashcards',
        machineName: 'H5P.Flashcards',
        url: `${EXPORTS}/flashcards-51-111820.h5p`,
        source: 'h5p.org-export'
    },
    {
        key: 'branching-scenario',
        contentType: 'Branching Scenario',
        machineName: 'H5P.BranchingScenario',
        url: `${HUB}/H5P.BranchingScenario`,
        source: 'h5p-hub',
        note: 'no working h5p.org export'
    },
    {
        key: 'interactive-video',
        contentType: 'Interactive Video',
        machineName: 'H5P.InteractiveVideo',
        url: `${HUB}/H5P.InteractiveVideo`,
        source: 'h5p-hub',
        note: 'h5p.org export interactive-video-2-618 is 404'
    },
    {
        key: 'multiple-choice',
        contentType: 'Multiple Choice',
        machineName: 'H5P.MultiChoice',
        url: `${EXPORTS}/multiple-choice-713.h5p`,
        source: 'h5p.org-export'
    },
    {
        key: 'course-presentation',
        contentType: 'Course Presentation',
        machineName: 'H5P.CoursePresentation',
        url: `${HUB}/H5P.CoursePresentation`,
        source: 'h5p-hub',
        note: 'h5p.org export course-presentation-21-21180 is 404'
    },
    {
        key: 'true-false',
        contentType: 'True/False Question',
        machineName: 'H5P.TrueFalse',
        url: `${EXPORTS}/true-false-question-34806.h5p`,
        source: 'h5p.org-export'
    },
    {
        key: 'fill-in-the-blanks',
        contentType: 'Fill in the Blanks',
        machineName: 'H5P.Blanks',
        url: `${EXPORTS}/fill-in-the-blanks-837.h5p`,
        source: 'h5p.org-export'
    },
    {
        key: 'memory-game',
        contentType: 'Memory Game',
        machineName: 'H5P.MemoryGame',
        url: `${EXPORTS}/memory-game-5-708.h5p`,
        source: 'h5p.org-export'
    }
];
