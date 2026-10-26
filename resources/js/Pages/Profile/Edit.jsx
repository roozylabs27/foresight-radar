import { Head } from "@inertiajs/react";
import { Building2Icon, MailIcon, ShieldCheckIcon, UserIcon } from "lucide-react";

import SettingsLayout from "@/Layouts/SettingsLayout";
import { Badge } from "@/Components/ui/badge";
import { cn } from "@/lib/utils";
import DeleteUserForm from "./Partials/DeleteUserForm";
import UpdatePasswordForm from "./Partials/UpdatePasswordForm";
import UpdateProfileInformationForm from "./Partials/UpdateProfileInformationForm";

function ProfileCard({ className, children }) {
    return (
        <div className={cn("rounded-xl border border-solid border-border bg-card p-5 text-card-foreground shadow-2xs lg:p-6", className)}>
            {children}
        </div>
    );
}

export default function Edit({ auth, mustVerifyEmail, status }) {
    const user = auth?.user;
    const roleName = auth?.role || "Staff";
    const tenantName = auth?.tenant?.name;

    return (
        <SettingsLayout auth={auth} title="Profil & Akun" activeTab="profile">
            <Head title="Profil & Akun" />

            <div className="flex flex-col gap-6">
                {/* Profile Overview Card */}
                <ProfileCard className="flex flex-col sm:flex-row items-center sm:items-start gap-5">
                    <span
                        aria-hidden="true"
                        className="flex size-16 shrink-0 items-center justify-center rounded-full bg-primary text-2xl font-bold text-primary-foreground shadow-xs"
                    >
                        {user?.name ? user.name.charAt(0).toUpperCase() : "U"}
                    </span>
                    <div className="flex flex-1 flex-col items-center sm:items-start text-center sm:text-left gap-1.5">
                        <div className="flex flex-wrap items-center justify-center sm:justify-start gap-2">
                            <h2 className="m-0 text-lg font-bold text-foreground">{user?.name}</h2>
                            <Badge variant="secondary" className="px-2.5 py-0.5 text-xs gap-1 font-medium">
                                <ShieldCheckIcon className="size-3.5 text-primary" />
                                <span>Peran: {roleName}</span>
                            </Badge>
                            {tenantName && (
                                <Badge variant="outline" className="px-2.5 py-0.5 text-xs gap-1 font-medium">
                                    <Building2Icon className="size-3.5 text-muted-foreground" />
                                    <span>{tenantName}</span>
                                </Badge>
                            )}
                        </div>
                        <p className="m-0 flex items-center gap-1.5 text-xs text-muted-foreground">
                            <MailIcon className="size-3.5" />
                            <span>{user?.email}</span>
                        </p>
                    </div>
                </ProfileCard>

                {/* Profile Information Form */}
                <ProfileCard>
                    <UpdateProfileInformationForm mustVerifyEmail={mustVerifyEmail} status={status} />
                </ProfileCard>

                {/* Password Update Form */}
                <ProfileCard>
                    <UpdatePasswordForm />
                </ProfileCard>

                {/* Delete Account Zone */}
                <ProfileCard className="border-destructive/40">
                    <DeleteUserForm />
                </ProfileCard>
            </div>
        </SettingsLayout>
    );
}
