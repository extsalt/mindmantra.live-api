<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * CourseModel represents the database table 'courses'.
 */
class CourseModel extends Model
{
    protected $table            = 'courses';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;
    protected $allowedFields    = [
        'slug',
        'title',
        'description',
        'thumbnail',
        'time_limit',
        'total_marks',
        'total_questions',
        'negative_marking',
        'is_published',
        'category',
        'created_at',
        'updated_at',
    ];

    // Dates
    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';

    /**
     * Retrieve paginated list of published courses with optional filtering.
     *
     * @return array{data: list<array<string, mixed>>, total: int, page: int, per_page: int, total_pages: int}
     */
    public function getPaginatedCourses(int $page = 1, int $perPage = 10, ?string $category = null, ?string $search = null): array
    {
        $builder = $this->where('is_published', 1);

        if (! empty($category)) {
            $builder->where('category', $category);
        }

        if (! empty($search)) {
            $builder->groupStart()
                ->like('title', $search)
                ->orLike('description', $search)
                ->groupEnd();
        }

        $total = $builder->countAllResults(false);

        $offset = max(0, ($page - 1) * $perPage);
        $courses = $builder->orderBy('id', 'DESC')->findAll($perPage, $offset);

        $totalPages = $perPage > 0 ? (int) ceil($total / $perPage) : 1;

        return [
            'data'        => $courses,
            'total'       => $total,
            'page'        => $page,
            'per_page'    => $perPage,
            'total_pages' => $totalPages,
        ];
    }

    /**
     * Find a course by numeric ID or slug.
     */
    public function findByIdOrSlug(int|string $identifier): ?array
    {
        if (is_numeric($identifier)) {
            $course = $this->find((int) $identifier);
            if ($course !== null) {
                return $course;
            }
        }

        return $this->where('slug', (string) $identifier)->first();
    }
}
