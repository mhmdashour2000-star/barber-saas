<x-layouts.guest>
    <x-slot:title>Privacy Policy - Barbar SaaS</x-slot:title>

    <article class="mx-auto max-w-4xl px-4 py-10 sm:px-6 sm:py-14 lg:px-8" aria-labelledby="privacy-title">
        <header class="mb-8">
            <a href="{{ route('home') }}" class="text-sm font-medium text-blue-600 underline underline-offset-4 hover:text-blue-800">Back to home</a>
            <h1 id="privacy-title" class="mt-5 text-3xl font-bold tracking-tight text-gray-900 sm:text-4xl">Privacy Policy</h1>
            <p class="mt-3 text-sm text-gray-500">Last updated: September 2026</p>
            <p class="mt-5 text-base leading-7 text-gray-700">
                Barbar SaaS is a barber salon appointment management service. This policy explains how information is
                handled when salons manage their business and customers use the automated booking service. WhatsApp
                features apply when a salon has enabled and configured the integration.
            </p>
        </header>

        <div class="space-y-8 rounded-xl border border-gray-200 bg-white p-5 text-base leading-7 text-gray-700 shadow-sm sm:p-8">
            <section aria-labelledby="information-heading">
                <h2 id="information-heading" class="text-xl font-semibold text-gray-900">Information we process</h2>
                <ul class="mt-3 list-disc space-y-3 pl-5">
                    <li><strong>Salon and business accounts:</strong> salon name, business contact details, location details if supplied, manager name and email, account credentials and operational settings.</li>
                    <li><strong>Customer information:</strong> customer name and WhatsApp phone number, associated with the salon the customer contacts. Customers do not need a separate application account to book through WhatsApp.</li>
                    <li><strong>Appointments and bookings:</strong> selected service and barber, date and time, duration, price, booking reference, appointment status and changes, including rescheduling and cancellation. Records may also include no-shows, cancellation violations and booking restrictions used by the salon.</li>
                    <li><strong>WhatsApp conversations and events:</strong> text and selections needed to follow the booking conversation, conversation progress, replies, message timing, processing and delivery events, and records that prevent duplicate processing.</li>
                    <li><strong>Employees and services:</strong> employee names, staff account information, active status, service assignments and availability, together with service names, durations and prices.</li>
                    <li><strong>Security and technical information:</strong> account activity, audit records, session information and relevant connection details, such as IP address and browser information, used to operate and protect the application.</li>
                </ul>
            </section>

            <section aria-labelledby="use-heading">
                <h2 id="use-heading" class="text-xl font-semibold text-gray-900">How information is used</h2>
                <p class="mt-3">We use information only to provide, secure, maintain and improve the service. This includes managing salon accounts, matching customers to the correct salon, showing availability, creating and managing appointments, answering booking inquiries, sending booking-related replies, enforcing booking restrictions and diagnosing service problems.</p>
                <p class="mt-3">The website uses session cookies to support functions such as account sign-in and request security. Please share only information needed for your booking and avoid sending sensitive documents or unrelated personal information.</p>
            </section>

            <section aria-labelledby="sharing-heading">
                <h2 id="sharing-heading" class="text-xl font-semibold text-gray-900">Salons, WhatsApp and service providers</h2>
                <p class="mt-3">The salon you contact uses your information to manage its appointments. Authorized salon managers can access their salon's records, and authorized platform administrators may access information needed to operate and secure the service.</p>
                <p class="mt-3">WhatsApp communication is provided through Meta WhatsApp Cloud API. Relevant phone numbers, messages and delivery information are processed through that service to receive your requests and deliver replies. Meta and WhatsApp have their own applicable terms and privacy practices. Using their technology does not mean Meta or WhatsApp endorses Barbar SaaS.</p>
                <p class="mt-3">Information may also be processed by infrastructure and service providers necessary to host, store, maintain and secure the application. Information may be disclosed where required by applicable law or necessary to address misuse and protect the service.</p>
            </section>

            <section aria-labelledby="security-heading">
                <h2 id="security-heading" class="text-xl font-semibold text-gray-900">Security</h2>
                <p class="mt-3">Reasonable safeguards include access controls, separation of salon records, password hashing, encryption of temporarily stored message content, and authenticity checks on incoming WhatsApp events. These measures help protect information from unauthorized access or misuse, but no storage or transmission method can guarantee absolute security.</p>
            </section>

            <section aria-labelledby="retention-heading">
                <h2 id="retention-heading" class="text-xl font-semibold text-gray-900">Retention and deletion</h2>
                <p class="mt-3">Account, booking, conversation and audit records are retained to support the service, appointment history, security and troubleshooting. There is no single fixed automatic deletion period for all records. An expired conversation does not mean its associated customer or booking history has been deleted.</p>
                <p class="mt-3">Temporary message content used for processing and delivery is cleared at certain processing stages; booking records, saved replies and delivery history may remain. Deletion requests are reviewed in light of the information involved, linked records and any applicable retention obligations. Where some information must be retained, the reason and scope can be explained when responding to the request.</p>
            </section>

            <section aria-labelledby="requests-heading">
                <h2 id="requests-heading" class="text-xl font-semibold text-gray-900">Access, correction and deletion requests</h2>
                <p class="mt-3">You can request access to, correction of or deletion of your personal data. For salon booking information, contact the salon you booked with through its usual business contact channel and ask for your request to be handled or forwarded to the platform operator.</p>
                <p class="mt-3">Identify the salon and the WhatsApp number used for the booking, describe your request and include a booking reference if relevant. We may need to verify that the information belongs to you before providing or changing it. Do not send passwords or unrelated sensitive information. Requests will be reviewed and handled in accordance with applicable requirements; this page does not promise an unverified response deadline.</p>
            </section>

            <section aria-labelledby="contact-heading">
                <h2 id="contact-heading" class="text-xl font-semibold text-gray-900">Contact</h2>
                <p class="mt-3">For questions about a salon's use of your booking information, contact that salon directly.</p>
                <div class="mt-4 rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm leading-6 text-amber-900">
                    <p class="font-semibold">Privacy contact email: [Placeholder — verified privacy contact email to be added]</p>
                    <p class="mt-1">A verified platform privacy email has not yet been published. This placeholder is not an email address. Until it is replaced, ask your salon to forward platform-related privacy requests to the service operator.</p>
                </div>
            </section>
        </div>
    </article>
</x-layouts.guest>
