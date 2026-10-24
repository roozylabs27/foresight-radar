import { Head } from "@inertiajs/react";
import { MailIcon, ShieldCheckIcon, UserIcon } from "lucide-react";

import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout";
import { Badge } from "@/Components/ui/badge";
import { cn } from "@/lib/utils";
import DeleteUserForm from "./Partials/DeleteUserForm";
import UpdatePasswordForm from "./Partials/UpdatePasswordForm";
import UpdateProfileInformationForm from "./Partials/UpdateProfileInformationForm";

function ProfileCard({ className, children }) {
    return (
        <div className={cn("rounded-lg border border-solid border-border bg-card p-5 text-card-foreground lg:p-6", className)}>
            {children}
        </div>
    );
}

export default function Edit({ auth, mustVerifyEmail, status }) {
    const user = auth?.user;
    const roleName = auth?.role || "Staff";

    return (
        <AuthenticatedLayout
            auth={auth}
            breadcrumbs={[{ label: "Profil Pengguna" }]}
            header={
                <div className="flex min-w-0 items-center gap-2.5">
                    <UserIcon aria-hidden="true" className="text-lg text-primary" />
                    <h1 className="m-0 truncate text-lg font-bold text-foreground">Pengaturan Profil</h1>
                </div>
            }
        >
            <Head title="Profil Pengguna" />

            <main className="mx-auto flex w-full max-w-[1200px] flex-1 flex-col gap-5 p-4 lg:px-6 lg:py-5">

                <div className="grid items-start gap-5 md:grid-cols-3">
                    <ProfileCard className="flex flex-col items-center gap-3 py-8 text-center md:sticky md:top-20">
                        <span
                            aria-hidden="true"
                            className="flex size-20 items-center justify-center rounded-full bg-primary text-[32px] font-semibold text-primary-foreground"
                        >
                            {user?.name ? user.name.charAt(0).toUpperCase() : "U"}
                        </span>
                        <div className="flex min-w-0 max-w-full flex-col items-center gap-1">
                            <h2 className="m-0 max-w-full truncate text-lg font-bold text-foreground">{user?.name}</h2>
                            <p className="m-0 flex max-w-full items-center gap-1.5 text-[13px] text-muted-foreground">
                                <MailIcon aria-hidden="true" />
                                <span className="truncate">{user?.email}</span>
                            </p>
                        </div>
                        <Badge variant="secondary" className="px-2.5 py-1 text-xs">
                            <ShieldCheckIcon aria-hidden="true" />
                            Peran: {roleName}
                        </Badge>
                    </ProfileCard>

                    <div className="flex min-w-0 flex-col gap-5 md:col-span-2">
                        <ProfileCard>
                            <UpdateProfileInformationForm mustVerifyEmail={mustVerifyEmail} status={status} />
                        </ProfileCard>
                        <ProfileCard>
                            <UpdatePasswordForm />
                        </ProfileCard>
                        <ProfileCard className="border-danger/40">
                            <DeleteUserForm />
                        </ProfileCard>
                    </div>
                </div>
            </main>
        </AuthenticatedLayout>
    );
}
