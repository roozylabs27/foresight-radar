import React, { useState } from "react";
import GuestLayout from "@/Layouts/GuestLayout";
import { Head, router } from "@inertiajs/react";
import { Alert, Button, Flex, Typography } from "antd";
import { LogoutOutlined, SendOutlined } from "@ant-design/icons";

const { Title, Paragraph } = Typography;

export default function VerifyEmail({ status }) {
    const [loading, setLoading] = useState(false);

    const submit = (e) => {
        e.preventDefault();
        setLoading(true);

        router.post(
            route("verification.send"),
            {},
            {
                onFinish: () => {
                    setLoading(false);
                },
            }
        );
    };

    const logout = (e) => {
        e.preventDefault();
        router.post(route("logout"));
    };

    return (
        <GuestLayout>
            <Head title="Verifikasi Email - Foresight Radar" />

            <Title level={3} style={{ marginBottom: 8, color: "#0f172a" }}>
                Verifikasi Alamat Email
            </Title>
            <Paragraph type="secondary" style={{ marginBottom: 20, fontSize: 14 }}>
                Terima kasih telah bergabung di Foresight Radar. Silakan verifikasi email Anda dengan mengklik tautan yang telah kami kirimkan.
            </Paragraph>

            {status === "verification-link-sent" && (
                <Alert
                    message="Tautan verifikasi baru telah berhasil dikirim ke alamat email Anda."
                    type="success"
                    showIcon
                    style={{ marginBottom: 24 }}
                />
            )}

            <form onSubmit={submit}>
                <Flex vertical gap={12} style={{ marginTop: 24 }}>
                    <Button
                        type="primary"
                        htmlType="submit"
                        loading={loading}
                        icon={<SendOutlined />}
                        block
                        size="large"
                    >
                        Kirim Ulang Email Verifikasi
                    </Button>

                    <Button
                        type="default"
                        onClick={logout}
                        icon={<LogoutOutlined />}
                        block
                    >
                        Keluar (Logout)
                    </Button>
                </Flex>
            </form>
        </GuestLayout>
    );
}
