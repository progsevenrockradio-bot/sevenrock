@extends('layouts.app') {{-- Asumiendo que tienes un layout principal, cámbialo si es distinto --}}

@section('content')
<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-dark text-white">
                    <h4 class="mb-0">Envía tu Maqueta a Seven Rock Radio</h4>
                </div>
                <div class="card-body p-4">
                    
                    {{-- Muestra mensajes de éxito --}}
                    @if(session('success'))
                        <div class="alert alert-success">
                            {{ session('success') }}
                        </div>
                    @endif

                    {{-- Muestra errores de validación --}}
                    @if($errors->any())
                        <div class="alert alert-danger">
                            <ul class="mb-0">
                                @foreach($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    <form action="{{ route('submissions.store') }}" method="POST" enctype="multipart/form-data">
                        @csrf

                        <div class="mb-3">
                            <label for="band_name" class="form-label">Nombre de la Banda <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="band_name" name="band_name" value="{{ old('band_name') }}" required>
                        </div>

                        <div class="mb-3">
                            <label for="song_title" class="form-label">Título de la Canción <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="song_title" name="song_title" value="{{ old('song_title') }}" required>
                        </div>

                        <div class="mb-3">
                            <label for="contact_email" class="form-label">Correo de Contacto <span class="text-danger">*</span></label>
                            <input type="email" class="form-control" id="contact_email" name="contact_email" value="{{ old('contact_email') }}" required>
                        </div>

                        <div class="mb-3">
                            <label for="social_link" class="form-label">Enlace a Redes Sociales (Opcional)</label>
                            <input type="url" class="form-control" id="social_link" name="social_link" value="{{ old('social_link') }}" placeholder="https://instagram.com/tubanda">
                        </div>

                        <div class="mb-4">
                            <label for="audio_file" class="form-label">Archivo de Audio (MP3, WAV, FLAC - Max 50MB) <span class="text-danger">*</span></label>
                            <input type="file" class="form-control" id="audio_file" name="audio_file" accept=".mp3,.wav,.flac" required>
                        </div>

                        <button type="submit" class="btn btn-primary w-100 py-2">Subir Maqueta</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
