<?php

namespace App\Http\Controllers;

use App\Models\Tag;
use App\Http\Resources\TagResource;
use Illuminate\Http\Request;

class TagController extends Controller
{
    public function __invoke()
    {
        return TagResource::collection(
            Tag::all()
        );
    }
}
