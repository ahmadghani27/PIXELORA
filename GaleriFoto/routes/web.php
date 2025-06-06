<?php

use App\Http\Controllers\Auth\PasswordController;
use App\Http\Controllers\PhotoController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\AlbumController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ArchiveController;
use App\Http\Controllers\FavoriteController;

// redirect agar langsung menuju ke halaman dashboard
Route::get('/', function () {
    return view('welcome'); 
    // return redirect('/login');
});


Route::middleware('auth')->group(function () {
    //Route Album
    Route::get('/album', [AlbumController::class, 'index'])->name('album');
    Route::post('/album', [AlbumController::class, 'store'])->name('album.store');
    Route::delete('/album/{id_album}', [AlbumController::class, 'destroy'])->name('album.destroy');
    Route::get('/album/{folder}', [AlbumController::class, 'show'])->name('album.show');
    Route::patch('/album/{id_album}', [AlbumController::class, 'update'])->name('album.update');
    Route::post('/album/{album}/add-photos', [AlbumController::class, 'addPhotos'])
        ->name('album.add-photos');

    //Route Foto
    Route::get('/foto', [PhotoController::class, 'index'])->name('foto');
    Route::post('/foto/single-upload', [PhotoController::class, 'store'])->name('foto.singleupload');
    Route::post('/foto/multi-upload', [PhotoController::class, 'massStore'])->name('foto.multiupload');
    Route::delete('/foto/single-delete', [PhotoController::class, 'destroy'])->name('foto.singledelete');
    Route::patch('/foto/edit-judul', [PhotoController::class, 'editJudul'])->name('foto.editjudul');
    Route::patch('/foto/single-arsip', [PhotoController::class, 'arsipkan'])->name('foto.singlearsip');
    Route::patch('/foto/single-pindahalbum', [PhotoController::class, 'pindahAlbum'])->name('foto.singlepindahalbum');
    Route::patch('/foto/favorite', [PhotoController::class, 'toggleFavorite'])->name('foto.togglefavorite');
    Route::get('/api/getActiveAlbum/{id}', [AlbumController::class, 'getActiveAlbum'])->name('api.getActiveAlbum');
    Route::get('/api/getAllActiveAlbum', [AlbumController::class,  'getAllActiveAlbum'])->name('api.getAllActiveAlbum');
    Route::get('/foto_access/{path}', [PhotoController::class, 'access'])
        ->where('path', '.*')
        ->name('foto.access');
    Route::post('/foto/unarchive', [PhotoController::class, 'unarsipkan'])
        ->name('photos.unarchive');
    Route::patch('/foto/multiple-pindahalbum', [PhotoController::class, 'massPindahAlbum'])->name('foto.multiplepindahalbum');
    Route::patch('/foto/multiple-delete', [PhotoController::class, 'massDestroy'])->name('foto.multipledelete');
    Route::patch('/foto/multiple-arsip', [PhotoController::class, 'massArsipkan'])->name('foto.multiplearsip');
    Route::patch('/foto/multipleunarsip', [PhotoController::class, 'massUnarsipkan'])->name('foto.multipleunarsip');
    Route::get('/api/foto/{id}', [PhotoController::class, 'api_get_detail_global_foto'])
        ->name('foto.detail');
    
    
    
    Route::delete('/raw-delete', [PhotoController::class, 'deleteRaw'])->name('deleteRaw');
    Route::get('/download-file/{path}', [PhotoController::class, 'downloadFoto'])
        ->where('path', '.*')
        ->name('downloadFile');

    //Route Profile
    Route::get('/akun', [ProfileController::class, 'edit'])->name('akun');
    Route::patch('/akun/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::patch('/akun/password', [PasswordController::class, 'update'])->name('update_password');
    Route::delete('/akun', [ProfileController::class, 'destroy'])->name('profile.destroy');

    //Route Arsip
    Route::patch('/raw-arsip', [PhotoController::class, 'arsipkanRaw'])->name('arsipkanRaw');
    Route::patch('/raw-unarsip', [PhotoController::class, 'unarsipkanRaw'])->name('unarsipkanRaw');
    Route::get('/arsip', [ArchiveController::class, 'show'])->name('arsip');
    Route::post('/arsip/verify', [ArchiveController::class, 'verify'])->name('arsip.verify');
    Route::get('/arsip/content', [ArchiveController::class, 'content'])->name('arsip.content');

    //Route Favorit
    Route::get('/favorit', [FavoriteController::class, 'index'])->name('favorit');
    Route::patch('/foto/unFavorite', [FavoriteController::class, 'unFavorite'])->name('favorit.unFavorite');
});

require __DIR__ . '/auth.php';
