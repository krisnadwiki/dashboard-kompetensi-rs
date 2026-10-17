<?php
Auth::logout();
Flash::set('success', 'Anda berhasil keluar dari sistem.', 'success');
redirect('/login');
