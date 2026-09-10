import React, { useState } from 'react';
import { router } from '@inertiajs/react';
import Card from '../Components/Card';
import Button from '../Components/Button';
import TextInput from '../Components/TextInput';
import InputLabel from '../Components/InputLabel';
import { Building2, MapPin, Stethoscope, User, Settings, CheckCircle2, ChevronRight, ChevronLeft } from 'lucide-react';

export default function Onboarding({
    step: initialStep = 1,
    practice = {},
    branch = {},
    operatories = [],
    doctorProfile = {},
    currencies = {},
    locales = {},
}) {
    const [activeStep, setActiveStep] = useState(initialStep);
    const [loading, setLoading] = useState(false);
    const [errors, setErrors] = useState({});

    // Step 1 State
    const [name, setName] = useState(practice.name || '');
    const [taxId, setTaxId] = useState(practice.tax_id || '');

    // Step 2 State
    const [branchName, setBranchName] = useState(branch?.name || `${practice.name || 'Clinic'} Main Branch`);
    const [branchCode, setBranchCode] = useState(branch?.code || 'MAIN');
    const [address, setAddress] = useState(branch?.address || '');
    const [phone, setPhone] = useState(branch?.phone || '');

    // Step 3 State
    const [operatoryList, setOperatoryList] = useState(
        operatories.length > 0 ? operatories : ['Operatory 1', 'Operatory 2']
    );
    const [newOperatory, setNewOperatory] = useState('');

    // Step 4 State
    const [specialty, setSpecialty] = useState(doctorProfile?.specialty || 'General Dentistry');
    const [licenseNumber, setLicenseNumber] = useState(doctorProfile?.license_number || '');
    const [commission, setCommission] = useState(doctorProfile?.default_commission_percentage || 40);

    // Step 5 State
    const [currency, setCurrency] = useState(practice.currency || 'EGP');
    const [locale, setLocale] = useState(practice.locale || 'en');
    const [timezone, setTimezone] = useState(practice.timezone || 'Africa/Cairo');

    const steps = [
        { id: 1, name: 'Practice Basics', icon: Building2 },
        { id: 2, name: 'Primary Branch', icon: MapPin },
        { id: 3, name: 'Operatories / Chairs', icon: Settings },
        { id: 4, name: 'Doctor Profile', icon: Stethoscope },
        { id: 5, name: 'Preferences', icon: User },
        { id: 6, name: 'Completion', icon: CheckCircle2 },
    ];

    const addOperatory = () => {
        if (newOperatory.trim()) {
            setOperatoryList([...operatoryList, newOperatory.trim()]);
            setNewOperatory('');
        }
    };

    const removeOperatory = (index) => {
        if (operatoryList.length > 1) {
            setOperatoryList(operatoryList.filter((_, i) => i !== index));
        }
    };

    const handleNext = async () => {
        setLoading(true);
        setErrors({});

        let payload = { step: activeStep };
        if (activeStep === 1) payload = { ...payload, name, tax_id: taxId };
        if (activeStep === 2) payload = { ...payload, branch_name: branchName, code: branchCode, address, phone };
        if (activeStep === 3) payload = { ...payload, operatories: operatoryList };
        if (activeStep === 4) payload = { ...payload, specialty, license_number: licenseNumber, default_commission_percentage: commission };
        if (activeStep === 5) payload = { ...payload, currency, locale, timezone };

        try {
            const res = await fetch('/onboarding/step', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                },
                body: JSON.stringify(payload),
            });

            const data = await res.json();
            if (!res.ok) {
                setErrors(data.errors || { general: data.message || 'Validation failed' });
            } else {
                setActiveStep(activeStep + 1);
            }
        } catch (err) {
            setErrors({ general: 'Network error occurred. Please try again.' });
        } finally {
            setLoading(false);
        }
    };

    const handleComplete = async () => {
        setLoading(true);
        try {
            const res = await fetch('/onboarding/complete', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                },
            });
            const data = await res.json();
            if (data.redirect) {
                window.location.href = data.redirect;
            } else {
                router.visit('/dashboard');
            }
        } catch (err) {
            setErrors({ general: 'Error completing setup. Please try again.' });
        } finally {
            setLoading(false);
        }
    };

    return (
        <div className="min-h-screen bg-slate-900 text-slate-100 flex flex-col justify-center py-12 px-4 sm:px-6 lg:px-8">
            <div className="max-w-4xl mx-auto w-full">
                <div className="text-center mb-8">
                    <h1 className="text-3xl font-bold text-teal-400">DentalCare Clinic Setup</h1>
                    <p className="mt-2 text-slate-400">Complete your clinic profile in a few quick steps</p>
                </div>

                {/* Progress Bar */}
                <div className="mb-8 bg-slate-800 p-4 rounded-xl shadow-lg border border-slate-700 flex justify-between items-center overflow-x-auto">
                    {steps.map((s) => {
                        const Icon = s.icon;
                        const isActive = activeStep === s.id;
                        const isDone = activeStep > s.id;

                        return (
                            <div key={s.id} className="flex items-center space-x-2 min-w-max px-2">
                                <div className={`w-10 h-10 rounded-full flex items-center justify-center font-bold text-sm transition-all ${
                                    isDone ? 'bg-teal-500 text-slate-950' : isActive ? 'bg-teal-600 text-white ring-2 ring-teal-400' : 'bg-slate-700 text-slate-400'
                                }`}>
                                    <Icon className="w-5 h-5" />
                                </div>
                                <span className={`text-xs font-medium hidden sm:inline ${isActive ? 'text-teal-400 font-bold' : isDone ? 'text-slate-300' : 'text-slate-500'}`}>
                                    {s.name}
                                </span>
                            </div>
                        );
                    })}
                </div>

                {/* Step Form Card */}
                <Card className="bg-slate-800/90 border-slate-700 p-8 shadow-2xl">
                    {errors.general && (
                        <div className="mb-6 p-4 bg-rose-500/20 border border-rose-500 text-rose-300 rounded-lg text-sm">
                            {errors.general}
                        </div>
                    )}

                    {/* Step 1 */}
                    {activeStep === 1 && (
                        <div className="space-y-6">
                            <h2 className="text-xl font-bold text-slate-100 border-b border-slate-700 pb-3">Step 1: Practice Basics</h2>
                            <div>
                                <InputLabel value="Clinic / Practice Name" className="text-slate-300" />
                                <TextInput
                                    type="text"
                                    className="w-full mt-1 bg-slate-900 border-slate-700 text-slate-100"
                                    value={name}
                                    onChange={(e) => setName(e.target.value)}
                                    placeholder="e.g. Bright Smile Dental Clinic"
                                />
                                {errors.name && <p className="text-rose-400 text-xs mt-1">{errors.name[0]}</p>}
                            </div>
                            <div>
                                <InputLabel value="Tax Identification Number (Optional)" className="text-slate-300" />
                                <TextInput
                                    type="text"
                                    className="w-full mt-1 bg-slate-900 border-slate-700 text-slate-100"
                                    value={taxId}
                                    onChange={(e) => setTaxId(e.target.value)}
                                    placeholder="e.g. TAX-987654321"
                                />
                            </div>
                        </div>
                    )}

                    {/* Step 2 */}
                    {activeStep === 2 && (
                        <div className="space-y-6">
                            <h2 className="text-xl font-bold text-slate-100 border-b border-slate-700 pb-3">Step 2: Primary Branch</h2>
                            <div>
                                <InputLabel value="Branch Name" className="text-slate-300" />
                                <TextInput
                                    type="text"
                                    className="w-full mt-1 bg-slate-900 border-slate-700 text-slate-100"
                                    value={branchName}
                                    onChange={(e) => setBranchName(e.target.value)}
                                />
                            </div>
                            <div className="grid grid-cols-2 gap-4">
                                <div>
                                    <InputLabel value="Branch Code" className="text-slate-300" />
                                    <TextInput
                                        type="text"
                                        className="w-full mt-1 bg-slate-900 border-slate-700 text-slate-100"
                                        value={branchCode}
                                        onChange={(e) => setBranchCode(e.target.value)}
                                    />
                                </div>
                                <div>
                                    <InputLabel value="Contact Phone" className="text-slate-300" />
                                    <TextInput
                                        type="text"
                                        className="w-full mt-1 bg-slate-900 border-slate-700 text-slate-100"
                                        value={phone}
                                        onChange={(e) => setPhone(e.target.value)}
                                    />
                                </div>
                            </div>
                            <div>
                                <InputLabel value="Address" className="text-slate-300" />
                                <TextInput
                                    type="text"
                                    className="w-full mt-1 bg-slate-900 border-slate-700 text-slate-100"
                                    value={address}
                                    onChange={(e) => setAddress(e.target.value)}
                                />
                            </div>
                        </div>
                    )}

                    {/* Step 3 */}
                    {activeStep === 3 && (
                        <div className="space-y-6">
                            <h2 className="text-xl font-bold text-slate-100 border-b border-slate-700 pb-3">Step 3: Operatories / Dental Chairs</h2>
                            <div className="space-y-2">
                                {operatoryList.map((op, idx) => (
                                    <div key={idx} className="flex items-center justify-between bg-slate-900 p-3 rounded-lg border border-slate-700">
                                        <span className="text-slate-200 font-medium">{op}</span>
                                        {operatoryList.length > 1 && (
                                            <button
                                                onClick={() => removeOperatory(idx)}
                                                className="text-rose-400 text-xs hover:underline"
                                            >
                                                Remove
                                            </button>
                                        )}
                                    </div>
                                ))}
                            </div>

                            <div className="flex space-x-2 pt-2">
                                <TextInput
                                    type="text"
                                    className="flex-1 bg-slate-900 border-slate-700 text-slate-100"
                                    value={newOperatory}
                                    onChange={(e) => setNewOperatory(e.target.value)}
                                    placeholder="Add operatory (e.g. Chair 3 - Ortho)"
                                />
                                <Button onClick={addOperatory} type="button" className="bg-slate-700 hover:bg-slate-600">
                                    Add
                                </Button>
                            </div>
                        </div>
                    )}

                    {/* Step 4 */}
                    {activeStep === 4 && (
                        <div className="space-y-6">
                            <h2 className="text-xl font-bold text-slate-100 border-b border-slate-700 pb-3">Step 4: Primary Doctor Profile</h2>
                            <div>
                                <InputLabel value="Specialty" className="text-slate-300" />
                                <TextInput
                                    type="text"
                                    className="w-full mt-1 bg-slate-900 border-slate-700 text-slate-100"
                                    value={specialty}
                                    onChange={(e) => setSpecialty(e.target.value)}
                                />
                            </div>
                            <div className="grid grid-cols-2 gap-4">
                                <div>
                                    <InputLabel value="License Number" className="text-slate-300" />
                                    <TextInput
                                        type="text"
                                        className="w-full mt-1 bg-slate-900 border-slate-700 text-slate-100"
                                        value={licenseNumber}
                                        onChange={(e) => setLicenseNumber(e.target.value)}
                                    />
                                </div>
                                <div>
                                    <InputLabel value="Default Commission Split (%)" className="text-slate-300" />
                                    <TextInput
                                        type="number"
                                        step="0.5"
                                        className="w-full mt-1 bg-slate-900 border-slate-700 text-slate-100"
                                        value={commission}
                                        onChange={(e) => setCommission(e.target.value)}
                                    />
                                </div>
                            </div>
                        </div>
                    )}

                    {/* Step 5 */}
                    {activeStep === 5 && (
                        <div className="space-y-6">
                            <h2 className="text-xl font-bold text-slate-100 border-b border-slate-700 pb-3">Step 5: Currency & Preferences</h2>
                            <div className="grid grid-cols-2 gap-4">
                                <div>
                                    <InputLabel value="Default Currency" className="text-slate-300" />
                                    <select
                                        className="w-full mt-1 bg-slate-900 border-slate-700 text-slate-100 rounded-md p-2"
                                        value={currency}
                                        onChange={(e) => setCurrency(e.target.value)}
                                    >
                                        {Object.entries(currencies).map(([code, label]) => (
                                            <option key={code} value={code}>{label}</option>
                                        ))}
                                    </select>
                                </div>
                                <div>
                                    <InputLabel value="Primary Language" className="text-slate-300" />
                                    <select
                                        className="w-full mt-1 bg-slate-900 border-slate-700 text-slate-100 rounded-md p-2"
                                        value={locale}
                                        onChange={(e) => setLocale(e.target.value)}
                                    >
                                        {Object.entries(locales).map(([code, label]) => (
                                            <option key={code} value={code}>{label}</option>
                                        ))}
                                    </select>
                                </div>
                            </div>
                        </div>
                    )}

                    {/* Step 6 */}
                    {activeStep === 6 && (
                        <div className="text-center py-8 space-y-4">
                            <div className="w-16 h-16 bg-teal-500/20 text-teal-400 rounded-full flex items-center justify-center mx-auto">
                                <CheckCircle2 className="w-10 h-10" />
                            </div>
                            <h2 className="text-2xl font-bold text-slate-100">Setup Complete!</h2>
                            <p className="text-slate-400 max-w-md mx-auto">
                                Your clinic environment has been successfully configured and is ready for use.
                            </p>
                        </div>
                    )}

                    {/* Action Buttons */}
                    <div className="mt-8 flex justify-between border-t border-slate-700 pt-6">
                        {activeStep > 1 && activeStep < 6 && (
                            <Button
                                type="button"
                                onClick={() => setActiveStep(activeStep - 1)}
                                className="bg-slate-700 hover:bg-slate-600 text-slate-200"
                            >
                                <ChevronLeft className="w-4 h-4 mr-1 inline" /> Back
                            </Button>
                        )}
                        {activeStep < 6 ? (
                            <Button
                                type="button"
                                disabled={loading}
                                onClick={handleNext}
                                className="ml-auto bg-teal-500 hover:bg-teal-400 text-slate-950 font-bold"
                            >
                                {loading ? 'Saving...' : 'Next Step'} <ChevronRight className="w-4 h-4 ml-1 inline" />
                            </Button>
                        ) : (
                            <Button
                                type="button"
                                disabled={loading}
                                onClick={handleComplete}
                                className="w-full bg-teal-500 hover:bg-teal-400 text-slate-950 font-bold text-lg py-3"
                            >
                                {loading ? 'Launching Dashboard...' : 'Enter Dashboard'}
                            </Button>
                        )}
                    </div>
                </Card>
            </div>
        </div>
    );
}
