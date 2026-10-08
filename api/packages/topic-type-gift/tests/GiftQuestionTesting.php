<?php

namespace EscolaLms\TopicTypeGift\Tests;

use EscolaLms\TopicTypeGift\Enum\QuestionTypeEnum;

trait GiftQuestionTesting
{
    public function questionDataProvider(): array
    {
        return [
            [
                'question' => 'Match the following countries with their corresponding capitals. {
                               =\=Canada -> Ottawa
                               =\=Italy  -> Rome
                               =\=Japan  -> Tokyo
                               =\=India  -> New Delhi
                               }',
                'type' => QuestionTypeEnum::MATCHING,
                'title' => '',
                'questionForStudent' => 'Match the following countries with their corresponding capitals.',
                'options' => [
                    'sub_questions' => [
                        '=Japan',
                        '=India',
                        '=Canada',
                        '=Italy',
                    ],
                    'sub_answers' => [
                        'New Delhi',
                        'Ottawa',
                        'Rome',
                        'Tokyo',
                    ],
                ],
            ],
            [
                'question' => 'Which answer equals 5\? {
                                ~%50%\= 3 + 2
                                ~%50%\= 2 + 3
                                ~%-100%\= 2 + 4
                            }',
                'type' => QuestionTypeEnum::MULTIPLE_CHOICE_WITH_MULTIPLE_RIGHT_ANSWERS,
                'title' => '',
                'questionForStudent' => 'Which answer equals 5?',
                'options' => [
                    'answers' => [
                        '= 3 + 2',
                        '= 2 + 3',
                        '= 2 + 4',
                    ],
                ]
            ],
            [
                'question' => 'Which answer equals 5\? {
                                ~ \= 2 + 2
                                = \= 2 + 3
                                ~ \= 2 + 4
                            }',
                'type' => QuestionTypeEnum::MULTIPLE_CHOICE,
                'title' => '',
                'questionForStudent' => 'Which answer equals 5?',
                'options' => [
                    'answers' => [
                        '= 2 + 2',
                        '= 2 + 3',
                        '= 2 + 4',
                    ],
                ]
            ],
            [
                'question' => 'Who\'s buried in Grant\'s tomb\?{=Grant ~no one ~Napoleon ~Churchill ~Mother Teresa }',
                'type' => QuestionTypeEnum::MULTIPLE_CHOICE,
                'title' => '',
                'questionForStudent' => 'Who\'s buried in Grant\'s tomb?',
                'options' => [
                    'answers' => [
                        'Grant',
                        'no one',
                        'Napoleon',
                        'Churchill',
                        'Mother Teresa',
                    ],
                ]
            ],
            [
                'question' => '::Grants tomb::Who is buried in Grant\'s tomb in New York City? { =Grant ~No one #Was true for 12 years, but Grant\'s remains were buried in the tomb in 1897 ~Napoleon #He was buried in France ~Churchill #He was buried in England ~Mother Teresa #She was buried in India }',
                'type' => QuestionTypeEnum::MULTIPLE_CHOICE,
                'title' => 'Grants tomb',
                'questionForStudent' => 'Who is buried in Grant\'s tomb in New York City?',
                'options' => [
                    'answers' => [
                        'Grant',
                        'No one',
                        'Napoleon',
                        'Churchill',
                        'Mother Teresa',
                    ],
                ]
            ],
            [
                'question' => 'What two people are entombed in Grant\'s tomb? { ~%-100%No one ~%50%Grant ~%50%Grant\'s wife ~%-100%Grant\'s father }',
                'type' => QuestionTypeEnum::MULTIPLE_CHOICE_WITH_MULTIPLE_RIGHT_ANSWERS,
                'title' => '',
                'questionForStudent' => 'What two people are entombed in Grant\'s tomb?',
                'options' => [
                    'answers' => [
                        'No one',
                        'Grant',
                        'Grant\'s wife',
                        'Grant\'s father',
                    ],
                ],
            ],
            [
                'question' => '::TrueStatement about Grant::Grant was buried in a tomb in New York City.{T}',
                'type' => QuestionTypeEnum::TRUE_FALSE,
                'title' => 'TrueStatement about Grant',
                'questionForStudent' => 'Grant was buried in a tomb in New York City.',
                'options' => [],
            ],
            [
                'question' => '// question: 0 name: FalseStatement using {FALSE} style
                               ::FalseStatement about sun::The sun rises in the West.{FALSE}',
                'type' => QuestionTypeEnum::TRUE_FALSE,
                'title' => 'FalseStatement about sun',
                'questionForStudent' => 'The sun rises in the West.',
                'options' => [],
            ],
            [
                'question' => 'Who\'s buried in Grant\'s tomb?{=Grant =Ulysses S. Grant =Ulysses Grant}',
                'type' => QuestionTypeEnum::SHORT_ANSWERS,
                'title' => '',
                'questionForStudent' => 'Who\'s buried in Grant\'s tomb?',
                'options' => [],
            ],
            [
                'question' => 'Two plus two equals {=four =4}',
                'type' => QuestionTypeEnum::SHORT_ANSWERS,
                'title' => '',
                'questionForStudent' => 'Two plus two equals',
                'options' => [],
            ],
            [
                'question' => 'Match the following countries with their corresponding capitals. {
                               =Canada -> Ottawa
                               =Italy  -> Rome
                               =Japan  -> Tokyo
                               =India  -> New Delhi
                               }',
                'type' => QuestionTypeEnum::MATCHING,
                'title' => '',
                'questionForStudent' => 'Match the following countries with their corresponding capitals.',
                'options' => [
                    'sub_questions' => [
                        'Japan',
                        'India',
                        'Canada',
                        'Italy',
                    ],
                    'sub_answers' => [
                        'New Delhi',
                        'Ottawa',
                        'Rome',
                        'Tokyo',
                    ],
                ],
            ],
            [
                'question' => 'Moodle costs {~lots of money =nothing ~a small amount} to download from moodle.org.',
                'type' => QuestionTypeEnum::MULTIPLE_CHOICE,
                'title' => '',
                'questionForStudent' => 'Moodle costs _____ to download from moodle.org.',
                'options' => [
                    'answers' => [
                        'lots of money',
                        'nothing',
                        'a small amount',
                    ]
                ],
            ],
            [
                'question' => 'What is the full simplification of the following expression, 2ab × 7ac? {~9abc~14abc~9ab²c=14ab²c}',
                'type' => QuestionTypeEnum::MULTIPLE_CHOICE,
                'title' => '',
                'questionForStudent' => 'What is the full simplification of the following expression, 2ab × 7ac?',
                'options' => [
                    'answers' => [
                        '9abc',
                        '14abc',
                        '9ab²c',
                        '14ab²c',
                    ],
                ],
            ],
            [
                'question' => 'The value of x⁻² is:{~x²~-x²=1/x²~-1/x²}',
                'type' => QuestionTypeEnum::MULTIPLE_CHOICE,
                'title' => '',
                'questionForStudent' => 'The value of x⁻² is:',
                'options' => [
                    'answers' => [
                        'x²',
                        '-x²',
                        '1/x²',
                        '-1/x²',
                    ],
                ],
            ],
            [
                'question' => 'When was Ulysses S. Grant born?{#1822:5}',
                'type' => QuestionTypeEnum::NUMERICAL_QUESTION,
                'title' => '',
                'questionForStudent' => 'When was Ulysses S. Grant born?',
                'options' => [],
            ],
            [
                'question' => 'What is the value of pi (to 3 decimal places)? {#3.14159:0.0005}.',
                'type' => QuestionTypeEnum::NUMERICAL_QUESTION,
                'title' => '',
                'questionForStudent' => 'What is the value of pi (to 3 decimal places)? _____ .',
                'options' => [],
            ],
            [
                'question' => 'What is the value of pi (to 3 decimal places)? {#3.141..3.142}.',
                'type' => QuestionTypeEnum::NUMERICAL_QUESTION,
                'title' => '',
                'questionForStudent' => 'What is the value of pi (to 3 decimal places)? _____ .',
                'options' => [],
            ],
            [
                'question' => 'Write a short biography of Dag Hammarskjöld. {}',
                'type' => QuestionTypeEnum::ESSAY,
                'title' => '',
                'questionForStudent' => 'Write a short biography of Dag Hammarskjöld.',
                'options' => [],
            ],
            [
                'question' => 'You can use your pencil and paper for these next math questions.',
                'type' => QuestionTypeEnum::DESCRIPTION,
                'title' => '',
                'questionForStudent' => 'You can use your pencil and paper for these next math questions.',
                'options' => [],
            ],
            [
                'question' => '::Jesus hometown::Jesus Christ was from {
                              ~Jerusalem#This was an important city, but the wrong answer.
                              ~%25%Bethlehem#He was born here, but not raised here.
                              ~%50%Galilee#You need to be more specific.
                              =Nazareth#Yes! That\'s right!
                              }.',
                'type' => QuestionTypeEnum::MULTIPLE_CHOICE_WITH_MULTIPLE_RIGHT_ANSWERS,
                'title' => 'Jesus hometown',
                'questionForStudent' => 'Jesus Christ was from _____ .',
                'options' => [
                    'answers' => [
                        'Jerusalem',
                        'Bethlehem',
                        'Galilee',
                        'Nazareth',
                    ],
                ],
            ],
            [
                'question' => '::Jesus hometown::Jesus Christ was from {
                                =Nazareth#Yes! That\'s right!
                                =%75%Nazereth#Right, but misspelled.
                                =%25%Bethlehem#He was born here, but not raised here.
                                }',
                'type' => QuestionTypeEnum::SHORT_ANSWERS,
                'title' => 'Jesus hometown',
                'questionForStudent' => 'Jesus Christ was from',
                'options' => [],
            ],
            [
                'question' => 'Who\'s buried in Grant\'s tomb\?{=a) Grant ~b) no one ~c) Napoleon ~d) Churchill ~e) Mother Teresa }',
                'type' => QuestionTypeEnum::MULTIPLE_CHOICE,
                'title' => '',
                'questionForStudent' => 'Who\'s buried in Grant\'s tomb?',
                'options' => [
                    'answers' => [
                        'a) Grant',
                        'b) no one',
                        'c) Napoleon',
                        'd) Churchill',
                        'e) Mother Teresa',
                    ],
                ]
            ],
        ];
    }
}
