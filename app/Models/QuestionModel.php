<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * QuestionModel represents the database table 'questions' and its course mappings.
 */
class QuestionModel extends Model
{
    protected $table            = 'questions';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;
    protected $allowedFields    = [
        'question_text',
        'image_url',
        'options',
        'correct_answer',
        'subject',
        'subject_id',
        'subject_module_id',
        'year',
        'quality_status',
        'is_active',
    ];

    /**
     * Retrieve paginated MCQs linked to a specific course.
     *
     * @return array{data: list<array<string, mixed>>, total: int, page: int, per_page: int, total_pages: int}
     */
    public function getPaginatedMcqsByCourseId(int $courseId, int $page = 1, int $perPage = 10): array
    {
        $builder = $this->db->table('course_questions cq')
            ->select('q.id, q.question_text, q.options, q.correct_answer, q.image_url, q.subject, q.year')
            ->join('questions q', 'q.id = cq.question_id')
            ->where('cq.course_id', $courseId)
            ->where('cq.is_active', 1)
            ->where('q.is_active', 1);

        $total = $builder->countAllResults(false);

        $offset = max(0, ($page - 1) * $perPage);
        $rows = $builder->orderBy('cq.id', 'ASC')->limit($perPage, $offset)->get()->getResultArray();

        // Parse options JSON for each MCQ
        $formatted = array_map(static function (array $row): array {
            $options = json_decode($row['options'] ?? '', true);
            $row['options'] = is_array($options) ? $options : [];
            $row['correct_answer'] = is_numeric($row['correct_answer']) ? (int) $row['correct_answer'] : $row['correct_answer'];
            $row['id'] = (int) $row['id'];

            return $row;
        }, $rows);

        $totalPages = $perPage > 0 ? (int) ceil($total / $perPage) : 1;

        return [
            'data'        => $formatted,
            'total'       => $total,
            'page'        => $page,
            'per_page'    => $perPage,
            'total_pages' => $totalPages,
        ];
    }
}
