<?php

namespace App\Controllers\Api\V1;

use App\Controllers\BaseController;
use App\Models\CourseModel;
use App\Models\QuestionModel;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * CourseController manages course catalog and associated MCQ questions.
 */
class CourseController extends BaseController
{
    /**
     * List courses with pagination and optional search/category filters.
     *
     * Query Parameters:
     *   - page: int (default: 1)
     *   - per_page: int (default: 10, max: 100)
     *   - category: string (optional)
     *   - search: string (optional)
     */
    public function index(): ResponseInterface
    {
        $page     = max(1, (int) $this->request->getGet('page') ?: 1);
        $perPage  = min(100, max(1, (int) $this->request->getGet('per_page') ?: 10));
        $category = $this->request->getGet('category');
        $search   = $this->request->getGet('search');

        $courseModel = new CourseModel();
        $result      = $courseModel->getPaginatedCourses(
            $page,
            $perPage,
            $category ? (string) $category : null,
            $search ? (string) $search : null
        );

        return $this->respond([
            'status'     => 200,
            'success'    => true,
            'message'    => 'Courses retrieved successfully.',
            'data'       => $result['data'],
            'pagination' => [
                'page'        => $result['page'],
                'per_page'    => $result['per_page'],
                'total'       => $result['total'],
                'total_pages' => $result['total_pages'],
            ],
        ], 200);
    }

    /**
     * Get single course details by ID or slug.
     */
    public function show(string|int $identifier): ResponseInterface
    {
        $courseModel = new CourseModel();
        $course      = $courseModel->findByIdOrSlug($identifier);

        if ($course === null) {
            return $this->respond([
                'status'   => 404,
                'success'  => false,
                'message'  => 'Course not found.',
            ], 404);
        }

        return $this->respond([
            'status'  => 200,
            'success' => true,
            'message' => 'Course retrieved successfully.',
            'data'    => $course,
        ], 200);
    }

    /**
     * List paginated Multiple Choice Questions (MCQs) for a course.
     *
     * Query Parameters:
     *   - page: int (default: 1)
     *   - per_page: int (default: 10, max: 100)
     */
    public function mcqs(string|int $identifier): ResponseInterface
    {
        $courseModel = new CourseModel();
        $course      = $courseModel->findByIdOrSlug($identifier);

        if ($course === null) {
            return $this->respond([
                'status'   => 404,
                'success'  => false,
                'message'  => 'Course not found.',
            ], 404);
        }

        $page    = max(1, (int) $this->request->getGet('page') ?: 1);
        $perPage = min(100, max(1, (int) $this->request->getGet('per_page') ?: 10));

        $questionModel = new QuestionModel();
        $result        = $questionModel->getPaginatedMcqsByCourseId((int) $course['id'], $page, $perPage);

        return $this->respond([
            'status'     => 200,
            'success'    => true,
            'message'    => 'Course MCQs retrieved successfully.',
            'course'     => [
                'id'    => (int) $course['id'],
                'slug'  => $course['slug'],
                'title' => $course['title'],
            ],
            'data'       => $result['data'],
            'pagination' => [
                'page'        => $result['page'],
                'per_page'    => $result['per_page'],
                'total'       => $result['total'],
                'total_pages' => $result['total_pages'],
            ],
        ], 200);
    }
}
