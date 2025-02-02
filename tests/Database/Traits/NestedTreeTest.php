<?php

namespace Winter\Storm\Tests\Database\Traits;

use Winter\Storm\Database\Model;
use Winter\Storm\Tests\Database\Fixtures\CategoryNested;
use Winter\Storm\Tests\DbTestCase;

class NestedTreeTest extends DbTestCase
{
    public function setUp(): void
    {
        parent::setUp();
        $this->seedSampleTree();
    }

    public function testGetNested()
    {
        $items = CategoryNested::getNested();

        // Eager loaded
        $items->each(function ($item) {
            $this->assertTrue($item->relationLoaded('children'));
        });

        $this->assertEquals(2, $items->count());
    }

    public function testGetAllRoot()
    {
        $items = CategoryNested::getAllRoot();

        // Not eager loaded
        $items->each(function ($item) {
            $this->assertFalse($item->relationLoaded('children'));
        });

        $this->assertEquals(2, $items->count());
    }

    public function testListsNested()
    {
        $array = CategoryNested::listsNested('name', 'id');
        $this->assertEquals([
            1 => 'Category Orange',
            2 => '&nbsp;&nbsp;&nbsp;Autumn Leaves',
            3 => '&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;September',
            4 => '&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;October',
            5 => '&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;November',
            6 => '&nbsp;&nbsp;&nbsp;Summer Breeze',
            7 => 'Category Green',
            8 => '&nbsp;&nbsp;&nbsp;Winter Snow',
            9 => '&nbsp;&nbsp;&nbsp;Spring Trees'
        ], $array);

        CategoryNested::flushDuplicateCache();

        $array = CategoryNested::listsNested('name', 'id', '--');
        $this->assertEquals([
            1 => 'Category Orange',
            2 => '--Autumn Leaves',
            3 => '----September',
            4 => '----October',
            5 => '----November',
            6 => '--Summer Breeze',
            7 => 'Category Green',
            8 => '--Winter Snow',
            9 => '--Spring Trees'
        ], $array);

        CategoryNested::flushDuplicateCache();

        $array = CategoryNested::listsNested('description', 'name', '**');
        $this->assertEquals([
            'Category Orange' => 'A root level test category',
            'Autumn Leaves' => '**Disccusion about the season of falling leaves.',
            'September' => '****The start of the fall season.',
            'October' => '****The middle of the fall season.',
            'November' => '****The end of the fall season.',
            'Summer Breeze' => '**Disccusion about the wind at the ocean.',
            'Category Green' => 'A root level test category',
            'Winter Snow' => '**Disccusion about the frosty snow flakes.',
            'Spring Trees' => '**Disccusion about the blooming gardens.'
        ], $array);
    }

    public function testListsNestedFromCollection()
    {
        $array = CategoryNested::get()->listsNested('custom_name', 'id', '...');
        $this->assertEquals([
            1 => 'Category Orange (#1)',
            2 => '...Autumn Leaves (#2)',
            3 => '......September (#3)',
            4 => '......October (#4)',
            5 => '......November (#5)',
            6 => '...Summer Breeze (#6)',
            7 => 'Category Green (#7)',
            8 => '...Winter Snow (#8)',
            9 => '...Spring Trees (#9)'
        ], $array);
    }

    public function testToNestedArray()
    {
        $array = CategoryNested::nestedArray('name', 'id');
        $this->assertEquals([
            1 => [
                "name" => "Category Orange",
                "children" => [
                    2 => [
                        "name" => "Autumn Leaves",
                        "children" => [
                            3 => [
                                "name" => "September",
                            ],
                            4 => [
                                "name" => "October",
                            ],
                            5 => [
                                "name" => "November",
                            ],
                        ],
                    ],
                    6 => [
                        "name" => "Summer Breeze",
                    ],
                ],
            ],
            7 => [
                "name" => "Category Green",
                "children" => [
                    8 => [
                        "name" => "Winter Snow",
                    ],
                    9 => [
                        "name" => "Spring Trees",
                    ],
                ],
            ],
        ], $array);

        CategoryNested::flushDuplicateCache();

        $array = CategoryNested::nestedArray('name');
        $this->assertEquals([
            0 => [
                "name" => "Category Orange",
                "children" => [
                    0 => [
                        "name" => "Autumn Leaves",
                        "children" => [
                            0 => [
                                "name" => "September",
                            ],
                            1 => [
                                "name" => "October",
                            ],
                            2 => [
                                "name" => "November",
                            ],
                        ],
                    ],
                    1 => [
                        "name" => "Summer Breeze",
                    ],
                ],
            ],
            1 => [
                "name" => "Category Green",
                "children" => [
                    0 => [
                        "name" => "Winter Snow",
                    ],
                    1 => [
                        "name" => "Spring Trees",
                    ],
                ],
            ],
        ], $array);
    }

    public function testToNestedArrayFromCollection()
    {
        $array = CategoryNested::get()->toNestedArray('name', 'id');
        $this->assertEquals([
            1 => [
                "name" => "Category Orange",
                "children" => [
                    2 => [
                        "name" => "Autumn Leaves",
                        "children" => [
                            3 => [
                                "name" => "September",
                            ],
                            4 => [
                                "name" => "October",
                            ],
                            5 => [
                                "name" => "November",
                            ],
                        ],
                    ],
                    6 => [
                        "name" => "Summer Breeze",
                    ],
                ],
            ],
            7 => [
                "name" => "Category Green",
                "children" => [
                    8 => [
                        "name" => "Winter Snow",
                    ],
                    9 => [
                        "name" => "Spring Trees",
                    ],
                ],
            ],
        ], $array);

        CategoryNested::flushDuplicateCache();

        $array = CategoryNested::get()->toNestedArray(['name', 'description'], 'id');
        $this->assertEquals([
            1 => [
                "name" => "Category Orange",
                'description' => 'A root level test category',
                "children" => [
                    2 => [
                        "name" => "Autumn Leaves",
                        'description' => 'Disccusion about the season of falling leaves.',
                        "children" => [
                            3 => [
                                "name" => "September",
                                'description' => 'The start of the fall season.'
                            ],
                            4 => [
                                "name" => "October",
                                'description' => 'The middle of the fall season.'
                            ],
                            5 => [
                                "name" => "November",
                                'description' => 'The end of the fall season.'
                            ],
                        ],
                    ],
                    6 => [
                        "name" => "Summer Breeze",
                        'description' => 'Disccusion about the wind at the ocean.'
                    ],
                ],
            ],
            7 => [
                "name" => "Category Green",
                'description' => 'A root level test category',
                "children" => [
                    8 => [
                        "name" => "Winter Snow",
                        'description' => 'Disccusion about the frosty snow flakes.'
                    ],
                    9 => [
                        "name" => "Spring Trees",
                        'description' => 'Disccusion about the blooming gardens.'
                    ],
                ],
            ],
        ], $array);
    }

    public function testToNestedArrayWithoutKey()
    {
        $array = CategoryNested::nestedArray('name');
        $this->assertEquals([
            [
                "name" => "Category Orange",
                "children" => [
                    [
                        "name" => "Autumn Leaves",
                        "children" => [
                            [
                                "name" => "September",
                            ],
                            [
                                "name" => "October",
                            ],
                            [
                                "name" => "November",
                            ],
                        ],
                    ],
                    [
                        "name" => "Summer Breeze",
                    ],
                ],
            ],
            [
                "name" => "Category Green",
                "children" => [
                    [
                        "name" => "Winter Snow",
                    ],
                    [
                        "name" => "Spring Trees",
                    ],
                ],
            ],
        ], $array);

        CategoryNested::flushDuplicateCache();

        $array = CategoryNested::nestedArray(['name', 'description']);
        $this->assertEquals([
            [
                "name" => "Category Orange",
                'description' => 'A root level test category',
                "children" => [
                    [
                        "name" => "Autumn Leaves",
                        'description' => 'Disccusion about the season of falling leaves.',
                        "children" => [
                            [
                                "name" => "September",
                                'description' => 'The start of the fall season.'
                            ],
                            [
                                "name" => "October",
                                'description' => 'The middle of the fall season.'
                            ],
                            [
                                "name" => "November",
                                'description' => 'The end of the fall season.'
                            ],
                        ],
                    ],
                    [
                        "name" => "Summer Breeze",
                        'description' => 'Disccusion about the wind at the ocean.'
                    ],
                ],
            ],
            [
                "name" => "Category Green",
                'description' => 'A root level test category',
                "children" => [
                    [
                        "name" => "Winter Snow",
                        'description' => 'Disccusion about the frosty snow flakes.'
                    ],
                    [
                        "name" => "Spring Trees",
                        'description' => 'Disccusion about the blooming gardens.'
                    ],
                ],
            ],
        ], $array);
    }

    public function testToNestedArrayFromCollectionWithoutKey()
    {
        $array = CategoryNested::get()->toNestedArray('name');
        $this->assertEquals([
            [
                "name" => "Category Orange",
                "children" => [
                    [
                        "name" => "Autumn Leaves",
                        "children" => [
                            [
                                "name" => "September",
                            ],
                            [
                                "name" => "October",
                            ],
                            [
                                "name" => "November",
                            ],
                        ],
                    ],
                    [
                        "name" => "Summer Breeze",
                    ],
                ],
            ],
            [
                "name" => "Category Green",
                "children" => [
                    [
                        "name" => "Winter Snow",
                    ],
                    [
                        "name" => "Spring Trees",
                    ],
                ],
            ],
        ], $array);

        CategoryNested::flushDuplicateCache();

        $array = CategoryNested::get()->toNestedArray(['name', 'description']);
        $this->assertEquals([
            [
                "name" => "Category Orange",
                'description' => 'A root level test category',
                "children" => [
                    [
                        "name" => "Autumn Leaves",
                        'description' => 'Disccusion about the season of falling leaves.',
                        "children" => [
                            [
                                "name" => "September",
                                'description' => 'The start of the fall season.'
                            ],
                            [
                                "name" => "October",
                                'description' => 'The middle of the fall season.'
                            ],
                            [
                                "name" => "November",
                                'description' => 'The end of the fall season.'
                            ],
                        ],
                    ],
                    [
                        "name" => "Summer Breeze",
                        'description' => 'Disccusion about the wind at the ocean.'
                    ],
                ],
            ],
            [
                "name" => "Category Green",
                'description' => 'A root level test category',
                "children" => [
                    [
                        "name" => "Winter Snow",
                        'description' => 'Disccusion about the frosty snow flakes.'
                    ],
                    [
                        "name" => "Spring Trees",
                        'description' => 'Disccusion about the blooming gardens.'
                    ],
                ],
            ],
        ], $array);
    }

    public function seedSampleTree()
    {
        Model::unguard();

        $orange = CategoryNested::create([
            'name' => 'Category Orange',
            'description' => 'A root level test category',
        ]);

        $autumn = $orange->children()->create([
            'name' => 'Autumn Leaves',
            'description' => 'Disccusion about the season of falling leaves.'
        ]);

        $autumn->children()->create([
            'name' => 'September',
            'description' => 'The start of the fall season.'
        ]);

        $autumn->children()->create([
            'name' => 'October',
            'description' => 'The middle of the fall season.'
        ]);

        $autumn->children()->create([
            'name' => 'November',
            'description' => 'The end of the fall season.'
        ]);

        $orange->children()->create([
            'name' => 'Summer Breeze',
            'description' => 'Disccusion about the wind at the ocean.'
        ]);

        $green = CategoryNested::create([
            'name' => 'Category Green',
            'description' => 'A root level test category',
        ]);

        $green->children()->create([
            'name' => 'Winter Snow',
            'description' => 'Disccusion about the frosty snow flakes.'
        ]);

        $green->children()->create([
            'name' => 'Spring Trees',
            'description' => 'Disccusion about the blooming gardens.'
        ]);

        Model::reguard();
    }
}
