<?php

namespace App\Http\Controllers\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreVideoCommentRequest;
use App\Http\Resources\CommentResource;
use App\Models\Comment;
use App\Models\Video;
use App\Services\ModerationService;

class VideoCommentController extends Controller
{
    public function index(Video $video)
    {
        if (!$video->is_public && auth()->id() !== $video->user_id && (!auth()->check() || !auth()->user()->is_admin)) {
            return response()->json(['message' => 'This video is private.'], 403);
        }

        $comments = $video->comments()
            ->with('user:id,name,username,avatar_url')
            ->latest()
            ->get();

        return response()->json([
            'comments' => CommentResource::collection($comments),
        ]);
    }

    public function store(StoreVideoCommentRequest $request, Video $video, ModerationService $moderationService)
    {
        $this->authorize('create', [Comment::class, $video]);
        $body = $request->validated('body');
        $moderation = $moderationService->moderateContent($body);

        if (isset($moderation['error'])) {
            return response()->json([
                'message' => 'Content moderation is currently unavailable.',
                'field' => 'body',
                'error' => $moderation['error'],
            ], 503);
        }

        if ($moderation['flagged'] ?? false) {
            return response()->json([
                'message' => 'This comment violates moderation policies.',
                'field' => 'body'
            ], 422);
        }

        $comment = Comment::create([
            'user_id' => auth()->id(),
            'video_id' => $video->id,
            'body' => $body,
        ]);

        $comment->load('user:id,name,username,avatar_url');

        return response()->json([
            'message' => 'Comment added successfully.',
            'comment' => new CommentResource($comment),
        ], 201);
    }
}
