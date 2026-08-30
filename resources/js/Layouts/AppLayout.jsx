import React from 'react';
import { Head } from '@inertiajs/react';
import Navbar from '../Components/Navbar';
import WelcomeNavbar from '../Components/WelcomeNavbar';
import Footer from '../Components/Footer';

export default function AppLayout({ title, auth, appName = 'DentalCare', children, isWelcome = false, customNavbar = null }) {
    return (
        <div className="min-h-screen bg-slate-950 text-slate-100 selection:bg-teal-500 selection:text-white font-sans antialiased overflow-x-hidden flex flex-col justify-between">
            <Head title={title} />

            {/* Ambient Background Glows */}
            <div className="fixed inset-0 pointer-events-none overflow-hidden z-0">
                <div className="absolute -top-40 left-1/2 -translate-x-1/2 w-[1000px] h-[500px] bg-gradient-to-tr from-teal-500/20 via-cyan-500/10 to-indigo-500/20 blur-[130px] rounded-full" />
                <div className="absolute top-1/3 -right-60 w-[600px] h-[600px] bg-teal-500/10 blur-[150px] rounded-full" />
                <div className="absolute bottom-10 -left-40 w-[600px] h-[600px] bg-blue-600/10 blur-[150px] rounded-full" />
            </div>

            {/* Navigation Header */}
            {customNavbar ? (
                customNavbar
            ) : isWelcome ? (
                <WelcomeNavbar auth={auth} appName={appName} />
            ) : (
                <Navbar auth={auth} appName={appName} />
            )}

            {/* Side Bar*/}



            {/* Page Content */}
            <main className="relative z-10 flex-grow">
                {children}
            </main>

            {/* Footer */}
            <Footer appName={appName} />
        </div>
    );
}
