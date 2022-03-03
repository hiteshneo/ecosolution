<!-- Header -->
<header class="header">
    <nav class="navbar navbar-expand-lg header-nav">
        <div class="navbar-header">
            <a id="mobile_btn" href="javascript:void(0);">
                <span class="bar-icon">
                    <span></span>
                    <span></span>
                    <span></span>
                </span>
            </a>
            <a href="{{ route('index') }}" class="navbar-brand logo">
                <img src="{{ asset('front-assets/img/logo.png') }}" class="img-fluid" alt="Logo">
            </a>
        </div>
        <div class="main-menu-wrapper">
            <div class="menu-header">
                <a href="{{ route('index') }}" class="menu-logo">
                    <img src="{{ asset('front-assets/img/logo.png') }}" class="img-fluid" alt="Logo">
                </a>
                <a id="menu_close" class="menu-close" href="javascript:void(0);">
                    <i class="fas fa-times"></i>
                </a>
            </div>
            <ul class="main-nav login-link">
                
                    @if (Auth::check())
                        @if(Auth::user()->role_id == DOCTOR_ROLE)
                            @include('layouts.doctorMenu')
                        @elseif(Auth::user()->role_id == PATIENT_ROLE)
                            @include('layouts.patientMenu')
                        @elseif(Auth::user()->role_id == ATTENDER_ROLE)
                            @include('layouts.attenderMenu')
                        @endif
                    @else
                        <a class="nav-link header-login" href="{{ route('login') }}">login / Signup </a>
                    @endif
                
            </ul>
        </div>
        <ul class="nav header-navbar-rht">
        <li><a href="https://doccure-html.dreamguystech.com/template/pharmacy-index.html">Pharmacy</a></li>
            <li class="nav-item contact-item">
                <div class="header-contact-img">
                    <i class="far fa-hospital"></i>
                </div>
                <div class="header-contact-detail">
                    <p class="contact-header">Contact</p>
                    <p class="contact-info-header"> +1 315 369 5943</p>
                </div>
            </li>
            <li class="nav-item">
                @if (Auth::check())
                <li class="dropdown profile">
                    <a href="#" class="dropdown-toggle text-right profileicon" data-toggle="dropdown" role="button" aria-expanded="false">
                        @if(Auth::user()->role_id == DOCTOR_ROLE)
                            <i class="fa fa-user-md"></i> 
                        @else
                            <i class="fa fa-user"></i> 
                        @endif    
                        Account
                        <span class="caret"></span>
                    </a>
                    <ul class="dropdown-menu">
                        @if(Auth::user()->role_id == DOCTOR_ROLE)
                            @include('layouts.doctorMenu')
                        @elseif(Auth::user()->role_id == PATIENT_ROLE)
                            @include('layouts.patientMenu')
                        @elseif(Auth::user()->role_id == ATTENDER_ROLE)
                            @include('layouts.attenderMenu')
                        @endif
                    </ul>
                </li>
                @else
                <a class="nav-link header-login" href="{{ route('login') }}">login / Signup </a>
                @endif
            </li>
        </ul>
    </nav>
</header>
<!-- /Header -->