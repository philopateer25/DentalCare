import React from 'react';
import AppLayout from '../Layouts/AppLayout';

export default function Patients({ auth }) {
    return (
        <AppLayout title="Patients" auth={auth}>
            <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
                {/* Patients content will be coded here */}
            </div>
        </AppLayout>
    );
}
