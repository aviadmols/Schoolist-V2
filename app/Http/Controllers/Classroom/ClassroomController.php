<?php

namespace App\Http\Controllers\Classroom;

use App\Http\Controllers\Controller;
use App\Models\Classroom;
use App\Services\Classroom\ClassroomContextService;
use App\Services\Classroom\ClassroomService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Illuminate\Http\RedirectResponse;

class ClassroomController extends Controller
{
    /** @var ClassroomService */
    private ClassroomService $classroomService;

    /** @var ClassroomContextService */
    private ClassroomContextService $contextService;

    public function __construct(ClassroomService $classroomService, ClassroomContextService $contextService)
    {
        $this->classroomService = $classroomService;
        $this->contextService = $contextService;
    }

    /**
     * Show the classroom selection page.
     */
    public function index(): Response
    {
        return Inertia::render('Classroom/Select', [
            'classrooms' => auth()->user()->classrooms()->get(),
            'current_id' => $this->contextService->getCurrentClassroom()?->id,
        ]);
    }

    /**
     * Show the classroom creation page.
     */
    public function create(): Response
    {
        return Inertia::render('Classroom/Create');
    }

    /**
     * Store a newly created classroom.
     */
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
        ]);

        $classroom = $this->classroomService->createForUser($request->user(), $data);
        $this->contextService->setCurrentClassroom($classroom);

        return redirect()->route('landing')->with('success', 'Classroom created successfully.');
    }

    /**
     * Show the join classroom page.
     */
    public function showJoin(): Response
    {
        return Inertia::render('Classroom/Join');
    }

    /**
     * Join a classroom via code.
     */
    public function join(Request $request): RedirectResponse
    {
        $request->validate([
            'join_code' => ['required', 'string', 'size:10'],
        ]);

        $classroom = $this->classroomService->joinWithCode($request->user(), $request->join_code);

        if (!$classroom) {
            return back()->withErrors(['join_code' => 'Invalid join code.']);
        }

        $this->contextService->setCurrentClassroom($classroom);

        return redirect()->route('landing')->with('success', 'Joined classroom successfully.');
    }

    /**
     * Switch the current classroom context.
     */
    public function switch(Classroom $classroom): RedirectResponse
    {
        // Ensure user belongs to this classroom
        if (!$classroom->users()->where('user_id', auth()->id())->exists()) {
            abort(403);
        }

        $this->contextService->setCurrentClassroom($classroom);

        return redirect()->route('landing');
    }
}
