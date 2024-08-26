@extends('layouts.main-layout', ['attributes' => null])

@section('body')
<div class="flex flex-col min-h-[100dvh] bg-gradient-to-br from-[#03255B] to-[#011638] text-white">
    <header class="px-4 lg:px-6 h-14 flex items-center w-20">
        <x-glyph-only-logo class="text-white" />
    </header>
    <main class="flex-1 flex flex-col items-center justify-center px-4 md:px-6 gap-8">
        <div class="text-center space-y-4">
            <h1 class="text-4xl sm:text-5xl md:text-6xl font-bold tracking-tighter">A better portfolio</h1>
            <div class="flex justify-center items-center gap-4" title="Yeah, that's halloween.">
                <div class="bg-white/10 px-4 py-2 rounded-lg text-2xl font-bold" x-data="countdown"
                    x-init="startCountdown" id="countdown">
                    <span x-text="days"></span>d
                    <span x-text="hours"></span>h
                    <span x-text="minutes"></span>m
                    <span x-text="seconds"></span>s
                </div>
            </div>
        </div>
        <div class="max-w-lg space-y-4">
            <p class="text-md text-center">
                Investbrain is a smart open-source platform that consolidates your portfolios from different brokerages,
                tracks market performance across your portfolios, and gives you an AI-powered investment best friend.
            </p>
            <div class="flex flex-col items-center gap-2 pt-7">
                <p class="text-lg font-medium">Stay up to date on our progress!</p>
                <form class="flex gap-2 w-full"
                    action="https://mail.lumifylabs.com/subscribe/0dcb1b4e-8a73-46f6-b9b5-6a6c12ef2f73" method="post">

                    <input
                        class="flex rounded-md px-3 py-2 text-md ring-offset-background focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:cursor-not-allowed flex-1 bg-white/10 text-white placeholder:text-white/50"
                        placeholder="steve@savvyinvestor.com" name="email" type="email" />
                    <div style="position: absolute; left: -9999px">
                        <label for="website-robot">Your Website</label>
                        <input type="text" id="website-robot" name="robot" tabindex="-1" autocomplete="nope" />
                    </div>
                    <input type="hidden" name="tags" value="coming-soon-page" />
                    <button
                        class="rounded-md text-md font-medium ring-offset-background transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:pointer-events-none disabled:opacity-50 bg-primary text-primary-foreground hover:bg-primary/90 h-10 px-4 py-2"
                        type="submit">
                        Join Waitlist
                    </button>
                </form>
            </div>
            <div class="flex flex-col items-center gap-2 pt-7">
                <a target="_blank" href="https://github.com/investbrainapp/investbrain" title="We're open source!" class="">
                    <x-github-icon />
                </a>
            </div>
        </div>
    </main>
    <footer
        class="flex flex-col gap-2 sm:flex-row py-6 w-full shrink-0 items-center px-4 md:px-6 border-t border-white/20">
        <p class="text-xs text-white/50">© {{ date('Y') }} Investbrain. All rights reserved.</p>
        <nav class="sm:ml-auto flex gap-4 sm:gap-6">
            <a class="text-xs hover:underline underline-offset-4 text-white/50" href="{{ route('terms.show') }}">
                Terms
            </a>
            <a class="text-xs hover:underline underline-offset-4 text-white/50" href="{{ route('policy.show') }}">
                Privacy
            </a>
        </nav>
    </footer>
</div>

<script>
    function countdown() {
        return {
            days: 0,
            hours: 0,
            minutes: 0,
            seconds: 0,
            countdownDate: new Date("Oct 31, 2024 00:00:00").getTime(),

            startCountdown() {
                this.updateCountdown();
                setInterval(() => this.updateCountdown(), 1000);
            },

            updateCountdown() {
                const now = new Date().getTime();
                const distance = this.countdownDate - now;

                if (distance < 0) {
                    clearInterval();
                    document.getElementById("countdown").innerText = "Coming Soon!";
                    return;
                }

                this.days = Math.floor(distance / (1000 * 60 * 60 * 24));
                this.hours = Math.floor((distance % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
                this.minutes = Math.floor((distance % (1000 * 60 * 60)) / (1000 * 60));
                this.seconds = Math.floor((distance % (1000 * 60)) / 1000);
            }
        };
    }
</script>
@endsection