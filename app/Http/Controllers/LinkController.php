<?php

namespace App\Http\Controllers;

use App\Models\Click;
use App\Models\Link;
use App\Models\LinkTag;
use App\Models\Tag;
use App\Models\Vote;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class LinkController extends Controller
{
    public function searchAll(Request $request){
        try {
            $search = $request->search_text;
            
            $links = Link::where('user_id', Auth::user()->id)->get();

            if(!$search || $search === '') {
                return Base::SUCCESS('Succeess', []);
            }
            $query = DB::table('links')
                    ->join('link_tags', 'link_id', '=', 'links.id')
                    ->join('tags', 'tags.id', '=', 'link_tags.tag_id')
                    ->where('links.is_private', false)
                    ->where(function($query) use($search) {
                        return $query->where('links.title','like','%'.$search.'%')
                                ->orWhere('tags.name','like','%'.$search.'%');
                    });

            $links = $query
                        ->select('links.id', 'links.title', 'links.url', 'links.uuid', 'links.is_private')
                        ->orderBy('links.title', 'desc')->distinct()->get();

            foreach ($links as $link) {
                $tags = [];
                $ling_tags = LinkTag::where('link_id', $link->id)->get();
                foreach ($ling_tags as $ling_tag_i) {
                    $tag = Tag::find($ling_tag_i->tag_id);
                    array_push($tags, $tag);
                }
                $link->tags = $tags;
                $link->url_uuid = route('click', $link->uuid);
                $link->clicks = Click::where('link_id', $link->id)->count();
                $upvote = Vote::where([['link_id', $link->id], ['type', 'up']])->count();
                $downvote = Vote::where([['link_id', $link->id], ['type', 'down']])->count();
                $link->votes = $upvote - $downvote;
                $link->my_vote = Vote::where([['user_id', Auth::user()->id], ['link_id', $link->id]])->first();
            }
            
            return Base::SUCCESS('Succeess', $links);
        } catch (Exception $e) {
            return Base::ERROR('an error occurred!', $e->getMessage());
        }
    }

    public function index(Request $request){
        try {
            $links = Link::where('user_id', Auth::user()->id)->get();

            foreach ($links as $link) {
                $tags = [];
                $ling_tags = LinkTag::where('link_id', $link->id)->get();
                foreach ($ling_tags as $ling_tag_i) {
                    $tag = Tag::find($ling_tag_i->tag_id);
                    array_push($tags, $tag);
                }
                $link['tags'] = $tags;
                $link['url_uuid'] = route('click', $link->uuid);
                $link['clicks'] = Click::where('link_id', $link->id)->count();
                $upvote = Vote::where([['link_id', $link->id], ['type', 'up']])->count();
                $downvote = Vote::where([['link_id', $link->id], ['type', 'down']])->count();
                $link['votes'] = $upvote - $downvote;
                $link['my_vote'] = Vote::where([['user_id', Auth::user()->id], ['link_id', $link->id]])->first();
            }
            
            return Base::SUCCESS('Succeess', $links);
        } catch (Exception $e) {
            return Base::ERROR('an error occurred!', $e->getMessage());
        }
    }

    public function add(Request $request){
        $validator =  Validator::make($request->all(), [
            'title' => 'required|string',
            'url' => 'required|string',
            'is_private' => 'required',
            'tags' => 'required|array|distinct|min:1',
            "tags.*"  => "required|distinct|min:1",
        ]);
        if ($validator->fails()) return Base::ERROR($validator->errors()->first(), $validator->errors());
        try {
            foreach ($request->tags as $tag_i) {
                $tag_exist = Tag::find($tag_i);
                if(!$tag_exist) return Base::ERROR("Tag not found");
            }
            $user = Auth::user();

            $link = new Link();
            $link->title = $request->title;
            $link->url = $request->url;
            $link->user_id = $user->id;
            $link->uuid = (string) Str::uuid();;
            $link->image_url = $request->image_url;
            $link->is_private = $request->is_private;
            $link->save();

            foreach ($request->tags as $tag_i) {
                $link_tag = new LinkTag();
                $link_tag->tag_id = $tag_i;
                $link_tag->link_id = $link->id;
                $link_tag->save();
            }
            
            return Base::SUCCESS('Link Added.', $link);
        } catch (Exception $e) {
            return Base::ERROR('an error occurred!', $e->getMessage());
        }
    }

    public function update(Request $request){
        $validator =  Validator::make($request->all(), [
            'type' => 'required'
        ]);
        if ($validator->fails()) return Base::ERROR($validator->errors()->first(), $validator->errors());
        try {
            return Base::SUCCESS('Succeess');
        } catch (Exception $e) {
            return Base::ERROR('an error occurred!', $e->getMessage());
        }
    }
    
    public function delete(Request $request){
        $validator =  Validator::make($request->all(), [
            'type' => 'required'
        ]);
        if ($validator->fails()) return Base::ERROR($validator->errors()->first(), $validator->errors());
        try {
            return Base::SUCCESS('Succeess');
        } catch (Exception $e) {
            return Base::ERROR('an error occurred!', $e->getMessage());
        }
    }

    public function toggle(Request $request){
        $validator =  Validator::make($request->all(), [
            'type' => 'required'
        ]);
        if ($validator->fails()) return Base::ERROR($validator->errors()->first(), $validator->errors());
        try {
            return Base::SUCCESS('Succeess');
        } catch (Exception $e) {
            return Base::ERROR('an error occurred!', $e->getMessage());
        }
    }

    public function click($uuid){
        try {
            $ip = request()->ip();

            $link = Link::where('uuid', $uuid)->first();

            $click = new Click();
            $click->user_id = $link->user_id;
            $click->link_id = $link->id;
            $click->ip = $link->ip;
            $click->save();
            
            return redirect($link->url);
        } catch (Exception $e) {
            return Base::ERROR('An error occurred!', $e->getMessage());
        }
    }

    public function vote(Request $request){
        $validator =  Validator::make($request->all(), [
            'id' => 'required',
            'type' => 'required'
        ]);
        if ($validator->fails()) return Base::ERROR($validator->errors()->first(), $validator->errors());
        try {
            $link = Link::find($request->id);

            $old_votes = Vote::where([['user_id', Auth::user()->id], ['link_id', $link->id]])->get();
            foreach ($old_votes as $old_vote) {
                $old_vote->delete();
            }

            $vote = new Vote();
            $vote->user_id = Auth::user()->id;
            $vote->link_id = $request->id;
            $vote->type = $request->type;
            $vote->save();


            $tags = [];
            $ling_tags = LinkTag::where('link_id', $link->id)->get();
            foreach ($ling_tags as $ling_tag_i) {
                $tag = Tag::find($ling_tag_i->tag_id);
                array_push($tags, $tag);
            }
            $link['tags'] = $tags;
            $link['clicks'] = Click::where('link_id', $link->id)->count();
            $upvote = Vote::where([['link_id', $link->id], ['type', 'up']])->count();
            $downvote = Vote::where([['link_id', $link->id], ['type', 'down']])->count();
            $link['votes'] = $upvote - $downvote;
            $link['my_vote'] = Vote::where([['user_id', Auth::user()->id], ['link_id', $link->id]])->first();
            $link['url_uuid'] = route('click', $link->uuid);
            
            return Base::SUCCESS('Succeess', $link);
        } catch (Exception $e) {
            return Base::ERROR('An error occurred!', $e->getMessage());
        }
    }
    
}
