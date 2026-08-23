<?php

namespace App\Http\Controllers\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreVideoCommentRequest;
use App\Http\Resources\CommentResource;
use App\Models\Comment;
use App\Models\Video;

class VideoCommentController extends Controller
{
    public function index(Video $video)
    {
        if (! $video->is_public && auth()->id() !== $video->user_id && (! auth()->check() || ! auth()->user()->is_admin)) {
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

    public function store(StoreVideoCommentRequest $request, Video $video)
    {
        $this->authorize('create', [Comment::class, $video]);

        $comment = Comment::create([
            'user_id' => auth()->id(),
            'video_id' => $video->id,
            'body' => $request->validated('body'),
        ]);

        $comment->load('user:id,name,username,avatar_url');

        return response()->json([
            'message' => 'Comment added successfully.',
            'comment' => new CommentResource($comment),
        ], 201);
    }
}

