<?php

namespace EscolaLms\TemplatesEmail\TopicTypeProject;

class ProjectSolutionCreatedVariables extends CommonProjectSolutionVariables
{
    public static function defaultSectionsContent(): array
    {
        return [
            'title' => __('Solution has been submitted to the project ":topic"', [
                'topic' => self::VAR_TOPIC_TITLE,
            ]),
            'content' => self::wrapWithMjml(__('<h1>Hello :user_name!</h1><p>Solution has been submitted to the project :topic</p>', [
                'user_name' => self::VAR_USER_NAME,
                'topic' => self::VAR_TOPIC_TITLE,
            ])),
        ];
    }
}
