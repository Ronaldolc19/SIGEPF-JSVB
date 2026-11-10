import { HeartPulse } from 'lucide-react';

export default function AppLogo() {
    return (
        <>
            <div className="flex aspect-square size-8 items-center justify-center rounded-md bg-[#722f42] text-white">
                <HeartPulse className="size-5" aria-hidden="true" />
            </div>
            <div className="ml-1 grid flex-1 text-left text-sm">
                <span className="mb-0.5 truncate leading-none font-semibold">SIGEPF-JSVB</span>
            </div>
        </>
    );
}
