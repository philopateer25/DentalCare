import React, { useState } from 'react';
import AppLayout from '../../Layouts/AppLayout';
import WelcomeNavbar from '../../Components/WelcomeNavbar';
import Button from '../../Components/Button';
import Badge from '../../Components/Badge';
import Card from '../../Components/Card';
import TextInput from '../../Components/TextInput';
import InputLabel from '../../Components/InputLabel';
import OdontogramPreview from '../../Components/OdontogramPreview';
import {
    Calendar,
    Clock,
    ShieldCheck,
    Sparkles,
    Activity,
    Layers,
    CreditCard,
    Package,
    ArrowRight,
    CheckCircle2,
    Stethoscope,
    Lock,
    Users,
    HeartPulse
} from 'lucide-react';

export default function Welcome({ auth, appName = 'DentalCare' }) {
    const [selectedService, setSelectedService] = useState('general');
    const [bookingDate, setBookingDate] = useState('');
    const [bookingDoctor, setBookingDoctor] = useState('dr-sarah');
    const [patientName, setPatientName] = useState('');
    const [patientPhone, setPatientPhone] = useState('');
    const [bookingSubmitted, setBookingSubmitted] = useState(false);


    const services = [
        {
            id: 'general',
            title: 'General & Preventive',
            desc: 'Comprehensive checkups, ultrasonic scaling, fluoride treatments, and digital X-rays.',
            icon: ShieldCheck,
            badge: 'Essential',
        },
        {
            id: 'cosmetic',
            title: 'Cosmetic & Veneers',
            desc: 'Digital smile design, porcelain veneers, teeth whitening, and aesthetic bonding.',
            icon: Sparkles,
            badge: 'Popular',
        },
        {
            id: 'ortho',
            title: 'Orthodontics & Aligners',
            desc: 'Clear invisible aligners, ceramic braces, digital tracking, and retainers.',
            icon: Layers,
            badge: 'Advanced',
        },
        {
            id: 'implants',
            title: 'Implants & Oral Surgery',
            desc: 'Guided 3D bone regeneration, titanium implant fixtures, and sinus lifts.',
            icon: Activity,
            badge: 'Specialist',
        },
    ];

    const stats = [
        { label: 'Registered Patients', value: '4,850+', icon: Users },
        { label: 'Successful Procedures', value: '12,400+', icon: CheckCircle2 },
        { label: 'Specialist Dentists', value: '18+', icon: Stethoscope },
        { label: 'Patient Satisfaction', value: '99.4%', icon: HeartPulse },
    ];

    const features = [
        {
            title: 'Interactive Odontogram & Perio Charting',
            description: 'Full FDI & Universal tooth condition mapping, periodontal pocket depth graphing, and treatment history per surface.',
            icon: Layers,
            color: 'from-teal-500 to-emerald-600',
        },
        {
            title: 'Multi-Chair Operatory Scheduling',
            description: 'Real-time drag-and-drop appointment grid with doctor availability, room allocation, and SMS notifications.',
            icon: Calendar,
            color: 'from-blue-500 to-cyan-600',
        },
        {
            title: 'Automated Billing & Installments',
            description: 'Itemized procedure invoicing, multi-part installment schedules, insurance claims, and doctor commission splits.',
            icon: CreditCard,
            color: 'from-indigo-500 to-purple-600',
        },
        {
            title: 'Dental Lab & Inventory Sync',
            description: 'Track shade selections, impressions, crowns, and batch lot numbers with automatic stock decrementing.',
            icon: Package,
            color: 'from-amber-500 to-orange-600',
        },
    ];

    return (
        <AppLayout
            title="Modern Dental Practice Management & Care"
            auth={auth}
            appName={appName}
            isWelcome={true}
            customNavbar={<WelcomeNavbar auth={auth} appName={appName} />}
        >
            {/* Hero Section */}
            <section className="pt-12 pb-20 lg:pt-24 lg:pb-32">
                <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                    <div className="grid grid-cols-1 lg:grid-cols-12 gap-12 lg:gap-8 items-center">

                        {/* Left Column */}
                        <div className="lg:col-span-7 space-y-8">
                            {/* <Badge variant="teal" className="py-1.5 px-3.5 text-xs sm:text-sm">
                                <Sparkles className="w-4 h-4 text-teal-400" />
                                <span>Laravel 13 • Filament 3 • Inertia.js • React 19</span>
                            </Badge> */}

                            <h1 className="text-4xl sm:text-6xl font-extrabold tracking-tight text-white leading-[1.1]">
                                Precision Dental Care <br />
                                <span className="bg-gradient-to-r from-teal-300 via-cyan-400 to-indigo-300 bg-clip-text text-transparent">
                                    Crafted for Healthy Smiles
                                </span>
                            </h1>

                            <p className="text-lg text-slate-300 max-w-2xl leading-relaxed">
                                Experience elevated dental health with state-of-the-art diagnostic imaging,
                                seamless online booking, and clinical practice management for modern dental clinics.
                            </p>

                            {/* Action Buttons */}
                            <div className="flex flex-wrap items-center gap-4">
                                <a href="#booking">
                                    <Button variant="primary" size="lg">
                                        <Calendar className="w-5 h-5" />
                                        Schedule Appointment
                                    </Button>
                                </a>

                                <a href="/admin">
                                    <Button variant="secondary" size="lg">
                                        <Lock className="w-5 h-5 text-teal-400" />
                                        Open Filament Portal
                                    </Button>
                                </a>
                            </div>

                            {/* Feature Badges */}
                            <div className="pt-4 flex flex-wrap gap-6 text-xs sm:text-sm text-slate-400 border-t border-slate-800/80">
                                <div className="flex items-center gap-2">
                                    <CheckCircle2 className="w-4 h-4 text-teal-400" />
                                    <span>Instant Digital Confirmation</span>
                                </div>
                                <div className="flex items-center gap-2">
                                    <CheckCircle2 className="w-4 h-4 text-teal-400" />
                                    <span>3D Odontogram Charting</span>
                                </div>
                                <div className="flex items-center gap-2">
                                    <CheckCircle2 className="w-4 h-4 text-teal-400" />
                                    <span>Zero-Wait Reception</span>
                                </div>
                            </div>
                        </div>

                        {/* Right Column: Interactive Odontogram & Operatory Live Preview */}
                        <div className="lg:col-span-5">
                            <OdontogramPreview />
                        </div>
                    </div>
                </div>
            </section>

            {/* Stats Bar */}
            <section className="border-y border-slate-800/80 bg-slate-900/40 backdrop-blur-md py-12">
                <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                    <div className="grid grid-cols-2 md:grid-cols-4 gap-8">
                        {stats.map((stat, idx) => {
                            const IconComponent = stat.icon;
                            return (
                                <div key={idx} className="flex flex-col items-center md:items-start space-y-2">
                                    <div className="flex items-center gap-2.5 text-teal-400">
                                        <IconComponent className="w-5 h-5" />
                                        <span className="text-3xl sm:text-4xl font-extrabold text-white tracking-tight">{stat.value}</span>
                                    </div>
                                    <span className="text-xs sm:text-sm text-slate-400 font-medium">{stat.label}</span>
                                </div>
                            );
                        })}
                    </div>
                </div>
            </section>

            {/* Clinical Management Features */}
            <section id="features" className="py-24">
                <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                    <div className="text-center max-w-3xl mx-auto space-y-4 mb-16">
                        <span className="text-xs font-bold uppercase tracking-widest text-teal-400">End-to-End Dentistry System</span>
                        <h2 className="text-3xl sm:text-4xl font-extrabold text-white tracking-tight">
                            Built for modern clinics and dental specialists
                        </h2>
                        <p className="text-slate-400 text-sm sm:text-base">
                            Everything from chairside charting to back-office billing, lab management, and patient scheduling.
                        </p>
                    </div>

                    <div className="grid grid-cols-1 md:grid-cols-2 gap-8">
                        {features.map((feat, idx) => {
                            const IconComponent = feat.icon;
                            return (
                                <Card key={idx} hover className="p-8 group relative overflow-hidden">
                                    <div className="flex items-start gap-5">
                                        <div className={`p-4 rounded-2xl bg-gradient-to-tr ${feat.color} text-white shadow-lg shrink-0`}>
                                            <IconComponent className="w-6 h-6" />
                                        </div>
                                        <div className="space-y-2">
                                            <h3 className="text-xl font-bold text-white group-hover:text-teal-300 transition-colors">
                                                {feat.title}
                                            </h3>
                                            <p className="text-sm text-slate-400 leading-relaxed">
                                                {feat.description}
                                            </p>
                                        </div>
                                    </div>
                                </Card>
                            );
                        })}
                    </div>
                </div>
            </section>

            {/* Interactive Booking */}
            <section id="booking" className="py-20 bg-slate-900/50 border-t border-slate-800/80">
                <div className="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
                    <Card className="p-8 sm:p-12 shadow-2xl space-y-8">
                        <div className="text-center space-y-2">
                            <span className="text-xs font-bold uppercase tracking-widest text-teal-400">Easy Online Reservation</span>
                            <h3 className="text-2xl sm:text-3xl font-extrabold text-white">Book Your Dental Consultation</h3>
                            <p className="text-slate-400 text-sm">Select your care preference and schedule a quick evaluation.</p>
                        </div>

                        {bookingSubmitted ? (
                            <div className="p-8 rounded-2xl bg-teal-500/10 border border-teal-500/30 text-center space-y-3">
                                <CheckCircle2 className="w-12 h-12 text-teal-400 mx-auto" />
                                <h4 className="text-lg font-bold text-white">Appointment Request Received!</h4>
                                <p className="text-sm text-slate-300">
                                    Thank you {patientName || 'valued patient'}, our receptionist will confirm your slot and send an SMS reminder to {patientPhone || 'your phone'}.
                                </p>
                                <Button
                                    variant="secondary"
                                    size="sm"
                                    onClick={() => setBookingSubmitted(false)}
                                    className="mt-4"
                                >
                                    Book Another Appointment
                                </Button>
                            </div>
                        ) : (
                            <form
                                onSubmit={(e) => {
                                    e.preventDefault();
                                    setBookingSubmitted(true);
                                }}
                                className="space-y-6"
                            >
                                {/* Service Selection */}
                                <div className="space-y-2">
                                    <InputLabel>Select Treatment Category</InputLabel>
                                    <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                        {services.map((svc) => (
                                            <button
                                                key={svc.id}
                                                type="button"
                                                onClick={() => setSelectedService(svc.id)}
                                                className={`p-4 rounded-2xl text-left border transition-all flex items-start justify-between ${selectedService === svc.id
                                                    ? 'bg-teal-500/15 border-teal-400 text-white shadow-md shadow-teal-500/10'
                                                    : 'bg-slate-950/60 border-slate-800 hover:border-slate-700 text-slate-400'
                                                    }`}
                                            >
                                                <div>
                                                    <div className="text-sm font-bold text-white">{svc.title}</div>
                                                    <div className="text-xs text-slate-400 mt-1 line-clamp-1">{svc.desc}</div>
                                                </div>
                                                <Badge variant="slate" className="shrink-0 ml-2">
                                                    {svc.badge}
                                                </Badge>
                                            </button>
                                        ))}
                                    </div>
                                </div>

                                {/* Patient Info & Schedule Grid */}
                                <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                    <div className="space-y-1.5">
                                        <InputLabel>Patient Full Name</InputLabel>
                                        <TextInput
                                            value={patientName}
                                            onChange={(e) => setPatientName(e.target.value)}
                                            placeholder="e.g. John Doe"
                                            required
                                        />
                                    </div>

                                    <div className="space-y-1.5">
                                        <InputLabel>Phone / WhatsApp Number</InputLabel>
                                        <TextInput
                                            type="tel"
                                            value={patientPhone}
                                            onChange={(e) => setPatientPhone(e.target.value)}
                                            placeholder="e.g. +1 (555) 019-2834"
                                            required
                                        />
                                    </div>

                                    <div className="space-y-1.5">
                                        <InputLabel>Preferred Specialist</InputLabel>
                                        <select
                                            value={bookingDoctor}
                                            onChange={(e) => setBookingDoctor(e.target.value)}
                                            className="w-full bg-slate-950/80 border border-slate-800 rounded-xl px-4 py-3 text-sm text-slate-100 focus:border-teal-400 focus:outline-none transition-colors"
                                        >
                                            <option value="dr-sarah">Dr. Sarah Vance (Orthodontics & Aesthetic)</option>
                                            <option value="dr-alex">Dr. Alex Rivera (Implantologist & Surgeon)</option>
                                            <option value="dr-clara">Dr. Clara Hughes (General & Pediatric)</option>
                                        </select>
                                    </div>

                                    <div className="space-y-1.5">
                                        <InputLabel>Preferred Date</InputLabel>
                                        <TextInput
                                            type="date"
                                            value={bookingDate}
                                            onChange={(e) => setBookingDate(e.target.value)}
                                            required
                                        />
                                    </div>
                                </div>

                                <Button
                                    type="submit"
                                    variant="primary"
                                    size="lg"
                                    className="w-full shadow-xl"
                                >
                                    <CheckCircle2 className="w-5 h-5" />
                                    Confirm Appointment Request
                                </Button>
                            </form>
                        )}
                    </Card>
                </div>
            </section>
        </AppLayout>
    );
}
