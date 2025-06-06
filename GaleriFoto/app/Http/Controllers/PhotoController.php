<?php

namespace App\Http\Controllers;

use App\Models\Photo;
use App\Models\Folder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Crypt;
use Intervention\Image\Facades\Image;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\log;
use Illuminate\Http\Exceptions\HttpResponseException;

class PhotoController extends Controller
{
    public function index()
    {
        $userId = Auth::id(); // Ambil ID user yang sedang login
        $sortOrder = request('sort', 'desc');
        $search = request('search');

        $query = Photo::where('user_id', $userId)
            ->where('is_archive', false);

        if (!empty($search)) {
            $query->where('photo_title', 'like', '%' . $search . '%');
        }

        $foto = $query->orderBy('created_at', $sortOrder)
            ->get()
            ->groupBy(function ($item) {
                $tanggal = Carbon::parse($item->created_at);

                if ($tanggal->isToday()) {
                    return 'Hari ini';
                } elseif ($tanggal->isYesterday()) {
                    return 'Kemarin';
                } else {
                    return $tanggal->translatedFormat('d M Y');
                }
            });

        // Fetch folders for the authenticated user
        $folders = Folder::where('user_id', $userId)->get();

        return view('photo.index', compact('foto', 'search', 'folders'));
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'photo' => 'required|file|mimes:jpg,jpeg,png|max:5048',
        ]);

        try {
            Log::info('Starting single photo upload', ['user_id' => $request->user()->id]);

            ini_set('memory_limit', '256M');
            set_time_limit(120);

            $folder = now()->format('Y/m');
            $fileName = 'foto-' . uniqid() . '.' . $request->photo->extension();
            $path = "photos/{$folder}/{$fileName}";

            // Log before image processing
            Log::debug('Processing image', [
                'original_size' => $request->file('photo')->getSize(),
                'mime_type' => $request->file('photo')->getMimeType()
            ]);

            $image = Image::make($request->file('photo'))
                ->encode('jpg', 70);

            Storage::disk('local')->put($path, $image);

            $photo = Photo::create([
                'user_id' => $request->user()->id,
                'file_path' => $path,
                'photo_title' => $request->title,
            ]);

            Log::info('Photo uploaded successfully', [
                'photo_id' => $photo->id,
                'file_path' => $path
            ]);

            return redirect()->back()->with([
                'status' => 'success',
                'message' => 'Foto berhasil diupload'
            ]);
        } catch (\Throwable $e) {
            Log::error('Photo upload failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'user_id' => $request->user()->id ?? null
            ]);

            return redirect()->back()->with([
                'status' => 'error',
                'message' => 'Gagal upload foto: ' . $e->getMessage()
            ]);
        }
    }

    public function massStore(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'photo.*' => 'required|image|mimes:jpg,jpeg,png|max:5048',
            'title.*' => 'required|string|max:255',
        ], [
            'title.*.required' => 'Judul foto wajib diisi.',
            'title.*.string'   => 'Judul foto harus berupa teks.',
            'title.*.max'      => 'Judul foto tidak boleh lebih dari 255 karakter.',
            'photo.*.required' => 'File foto wajib diunggah.',
            'photo.*.image'    => 'File yang diunggah harus berupa gambar.',
            'photo.*.mimes'    => 'Format file yang diperbolehkan adalah JPG, JPEG, atau PNG.',
            'photo.*.max'      => 'Ukuran file maksimal adalah 5MB (5048 KB).',
        ]);

        if ($validator->fails()) {
            Log::warning('Mass upload validation failed', [
                'errors' => $validator->errors()->all(),
                'user_id' => $request->user()->id
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Validasi gagal.',
                'errors' => $validator->errors()
            ], 422);
        }

        $maxFiles = 20; // or 50 if you prefer
        if (count($request->file('photo')) > $maxFiles) {
            return response()->json([
                'status' => 'error',
                'message' => "Maksimum {$maxFiles} foto dapat diunggah sekaligus."
            ], 422);
        }

        DB::beginTransaction();
        $uploadedFiles = [];
        $folder = now()->format('Y/m');

        try {
            ini_set('memory_limit', '512M');
            set_time_limit(120);

            foreach ($request->file('photo') as $index => $photo) {
                $judul = $request->input('title')[$index];
                $fileName = 'foto-' . uniqid() . '.' . $photo->extension();
                $path = "photos/{$folder}/{$fileName}";

                $image = Image::make($photo)
                    ->interlace()
                    ->encode('jpg', 70);

                Storage::disk('local')->put($path, $image);

                $uploadedPhoto = Photo::create([
                    'user_id' => $request->user()->id,
                    'file_path' => $path,
                    'photo_title' => $judul,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                $uploadedFiles[] = [
                    'id' => $uploadedPhoto->id,
                    'title' => $judul,
                    'filename' => $fileName,
                    'path' => $path
                ];
            }

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => count($uploadedFiles) . ' foto berhasil diupload.',
                'uploadedFiles' => $uploadedFiles,
                'data' => $uploadedFiles, // For backward compatibility
                'redirect' => url()->previous() // Crucial for closing modal
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();

            // Cleanup uploaded files
            foreach ($uploadedFiles as $file) {
                try {
                    Storage::disk('local')->delete($file['path']);
                } catch (\Throwable $cleanupError) {
                    Log::error('Cleanup failed: ' . $cleanupError->getMessage());
                }
            }

            return response()->json([
                'status' => 'error',
                'message' => 'Gagal upload foto: ' . $e->getMessage()
            ], 500);
        }
    }

    public function destroy(Request $request)
    {
        try {
            $decryptedId = Crypt::decryptString($request->id_foto);

            $foto = Photo::findOrFail($decryptedId);
            $foto->delete();

            return redirect()->back()->with([
                'status' => 'success',
                'message' => 'Foto berhasil dihapus.'
            ]);
        } catch (\Exception $e) {
            return redirect()->back()->with([
                'status' => 'error',
                'message' => 'Gagal hapus foto: ' . $e->getMessage()
            ]);
        }
    }

    public function massDestroy(Request $request)
    {
        $request->validateWithBag("massDelete", [
            'id_foto' => ['required'],
        ], [
            'id_foto.required' => 'Tidak ada foto yang dipilih',
        ]);

        try {
            $id_foto = json_decode($request->id_foto, true);

            foreach ($id_foto as $foto_id) {
                $foto = Photo::findOrFail($foto_id);
                $foto->delete();
            }

            return redirect()->back()->with([
                'status' => 'success',
                'message' => count($id_foto) . ' foto berhasil dihapus'
            ]);
        } catch (\Exception $e) {
            return redirect()->back()->with([
                'status' => 'error',
                'message' => 'Gagal hapus foto: ' . $e->getMessage()
            ]);
        }
    }

    public function editJudul(Request $request)
    {
        try {
            $decryptedId = Crypt::decryptString($request->id_foto);

            $foto = Photo::findOrFail($decryptedId);

            $foto->update([
                'photo_title' => $request->new_judul,
            ]);

            return redirect()->back()->with([
                'status' => 'success',
                'message' => 'Judul berhasil diperbarui'
            ]);
        } catch (\Exception $e) {
            return redirect()->back()->with([
                'status' => 'error',
                'message' => 'Gagal edit judul foto: ' . $e->getMessage()
            ]);
        }
    }

    public function arsipkan(Request $request)
    {
        $request->validate([
            'id_foto' => 'required|string'
        ]);

        try {
            $decryptedId = Crypt::decryptString($request->id_foto);

            $foto = Photo::findOrFail($decryptedId);
            $folderId = $foto->folder;

            // Update photo to archived
            $foto->update(['is_archive' => true]);

            return redirect()->back()->with([
                'status' => 'success',
                'message' => 'Foto berhasil diarsipkan'
            ]);
        } catch (\Exception $e) {
            return redirect()->back()->with([
                'status' => 'error',
                'message' => 'Gagal mengarsipkan foto: ' . $e->getMessage()
            ]);
        }
    }

    public function arsipkanRaw(Request $request)
    {
        try {
            $foto = Photo::findOrFail($request->id_photo);
            $foto->update(['is_archive' => true]);

            return redirect()->back()->with([
                'status' => 'success',
                'message' => 'Foto berhasil diarsipkan'
            ]);
        } catch (\Exception $e) {
            return redirect()->back()->with([
                'status' => 'error',
                'message' => 'Gagal mengarsipkan foto: ' . $e->getMessage()
            ]);
        }
    }

    public function deleteRaw(Request $request)
    {
        try {
            $foto = Photo::findOrFail($request->id_photo);
            $foto->delete();

            return redirect()->back()->with([
                'status' => 'success',
                'message' => 'Foto berhasil dihapus'
            ]);
        } catch (\Exception $e) {
            return redirect()->back()->with([
                'status' => 'error',
                'message' => 'Gagal menghapus foto: ' . $e->getMessage()
            ]);
        }
    }



    public function massArsipkan(Request $request)
    {
        $request->validateWithBag("massArsipkan", [
            'id_foto' => ['required'],
        ], [
            'id_foto.required' => 'Tidak ada foto yang dipilih',
        ]);

        try {
            $id_foto = json_decode($request->id_foto, true);

            foreach ($id_foto as $foto_id) {
                $foto = Photo::findOrFail($foto_id);
                $folderId = $foto->folder;

                // Update photo to archived
                $foto->update(['is_archive' => true]);

                // Check if this photo was a thumbnail for any album

            }

            return redirect()->back()->with([
                'status' => 'success',
                'message' => count($id_foto) . ' foto berhasil diarsipkan'
            ]);
        } catch (\Exception $e) {
            return redirect()->back()->with([
                'status' => 'error',
                'message' => 'Gagal mengarsipkan foto: ' . $e->getMessage()
            ]);
        }
    }

    public function toggleFavorite(Request $request)
    {
        $photo = Photo::findOrFail($request->id_foto);
        $photo->is_favorite = !$photo->is_favorite;
        $photo->save();

        return response()->json(['success' => true, 'is_favorite' => $photo->is_favorite]);
    }

    public function unarsipkan(Request $request)
    {
        $request->validate([
            'id_foto' => 'required|string'
        ]);

        try {
            $decryptedId = Crypt::decryptString($request->id_foto);

            $foto = Photo::where('id_photo', $decryptedId);

            $foto->update(['is_archive' => false]);

            return Redirect::route('arsip.content')->with([
                'status' => 'success',
                'message' => 'Foto berhasil dikeluarkan dari arsip'
            ]);
        } catch (\Exception $e) {
            return redirect()->back()->with([
                'status' => 'error',
                'message' => 'Gagal mengeluarkan dari arsip: ' . $e->getMessage()
            ]);
        }
    }

    public function unarsipkanRaw(Request $request)
    {
        $request->validate([
            'id_photo' => 'required'
        ]);

        try {
            $foto = Photo::findOrFail($request->id_photo);
            $foto->update(['is_archive' => false]);
            return redirect()->back()->with([
                'status' => 'success',
                'message' => 'Berhasil mengeluarkan foto dari arsip'
            ]);
        } catch (\Exception $e) {
            return redirect()->back()->with([
                'status' => 'error',
                'message' => 'Gagal mengeluarkan dari arsip: ' . $e->getMessage()
            ]);
        }
    }

    public function massUnarsipkan(Request $request)
    {
        $request->validateWithBag("massUnarsipkan", [
            'id_foto' => ['required'],
        ], [
            'id_foto.required' => 'Tidak ada foto yang dipilih',
        ]);

        try {
            $id_foto = json_decode($request->id_foto, true);

            foreach ($id_foto as $foto_id) {
                $foto = Photo::findOrFail($foto_id);
                $folderId = $foto->folder;

                // Update photo to unarchived
                $foto->update(['is_archive' => false]);
            }

            return redirect()->route('arsip.content')->with([
                'status' => 'success',
                'message' => count($id_foto) . ' foto berhasil dikeluarkan dari arsip'
            ]);
        } catch (\Exception $e) {
            return redirect()->route('arsip.content')->with([
                'status' => 'error',
                'message' => 'Gagal mengeluarkan foto dari arsip: ' . $e->getMessage()
            ]);
        }
    }

    public function pindahAlbum(Request $request)
    {
        try {
            $foto = Photo::findOrFail($request->id_foto);
            $album = Folder::findOrFail($request->folder_id);

            $foto->update([
                'folder' => $request->folder_id
            ]);

            return redirect()->back()->with([
                'status' => 'success',
                'message' => ('Foto berhasil dipindah ke album ' . $album->name_folder)
            ]);
        } catch (\Exception $e) {
            return redirect()->back()->with([
                'status' => 'error',
                'message' => 'Gagal memindah ke album: ' . $e->getMessage()
            ]);
        }
    }



    public function massPindahAlbum(Request $request)
    {
        $request->validateWithBag("massPindahAlbum", [
            'id_foto' => ['required'],
            'folder_id' => ['required'],
        ], [
            // Pesan error untuk id_foto
            'id_foto.required' => 'Tidak ada foto yang dipilih',
            // Pesan error untuk folder_id
            'folder_id.required' => 'Tidak ada album yang dipilih'
        ]);

        try {
            $id_foto = json_decode($request->id_foto, true);

            foreach ($id_foto as $foto_id) {
                $foto = Photo::findOrFail($foto_id);
                $foto->update([
                    'folder' => $request->folder_id
                ]);
            }

            return redirect()->back()->with([
                'status' => 'success',
                'message' => count($id_foto) . ' foto berhasil dipindah ke album'
            ]);
        } catch (\Exception $e) {
            return redirect()->back()->with([
                'status' => 'error',
                'message' => 'Gagal memindah ke album: ' . $e->getMessage()
            ]);
        }
    }

    public function access($path)
    {
        if (!Storage::disk('local')->exists($path)) {
            abort(404, 'File not found: ' . $path);
        }

        $fullPath = Storage::disk('local')->path($path);
        $mime = mime_content_type($fullPath);
        $file = file_get_contents($fullPath);

        return response($file, 200)->header('Content-Type', $mime);
    }

    public function api_get_detail_global_foto($foto_id)
    {
        try {
            $foto = Photo::findOrFail($foto_id);


            return response()->json([
                'status' => 'success',
                'message' => 'Berhasil memuat foto',
                'foto' => $foto
            ]);
        } catch (\Exception $e) {
            return redirect()->back()->with([
                'status' => 'error',
                'message' => 'Gagal memuat foto: ' . $e->getMessage(),
            ]);
        }
    }

    public function downloadFoto($path)
    {
        if (!Storage::disk('local')->exists($path)) {
            abort(404, 'File not found: ' . $path);
        }

        $absolutePath = Storage::disk('local')->path($path);
        return response()->download($absolutePath);
    }
}
