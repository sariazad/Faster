@php use App\Support\AlefYaStrings as S; @endphp
<div class="modal" id="notifModal" role="dialog" aria-modal="true" aria-labelledby="notifTitle">
    <div class="glass">
        <button class="modal-close" data-close aria-label="{{ S::get('app.close') }}"><i class="fa-solid fa-xmark"></i></button>
        <h2 id="notifTitle">{{ S::get('notifications.title') }}</h2>
        <div id="notifList"><p class="muted">{{ S::get('app.loading') }}</p></div>
    </div>
</div>
