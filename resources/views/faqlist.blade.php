@extends('layouts.master')
@section('content')
@section('page_title', 'Attenders')

<div class="content">
    <div class="container-fluid">
       <div class="row">
            <ul class="accordion-list">
                @foreach ($data as $item)
                <li>
                    <h3>{{ $item->question }}</h3>
                    <div class="answer">
                        {!! $item->answer  !!}
                        
                    </div>
                </li>
                @endforeach
            </ul>
        </div>
    </div>
</div>
@stop