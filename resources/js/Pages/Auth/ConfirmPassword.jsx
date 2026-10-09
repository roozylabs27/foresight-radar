import React, { useState } from "react";
import GuestLayout from "@/Layouts/GuestLayout";
import { Head, router } from "@inertiajs/react";
import { Alert, Button, Form, Input, Typography, theme } from "antd";
import { LockOutlined } from "@ant-design/icons";

const { Title, Paragraph } = Typography;

export default function ConfirmPassword({ errors: serverErrors }) {
    const [loading, setLoading] = useState(false);
    const { token } = theme.useToken();

    const onFinish = (values) => {
        setLoading(true);

        router.post(route("password.confirm"), values, {
            onError: () => {
                setLoading(false);
            },
            onFinish: () => {
                setLoading(false);
            },
        });
    };

    return (
        <GuestLayout>
            <Head title="Konfirmasi Kata Sandi - Foresight Radar" />

            <Title level={3} style={{ marginBottom: 8, color: "#0f172a" }}>
                Konfirmasi Kata Sandi
            </Title>
            <Paragraph type="secondary" style={{ marginBottom: 24, fontSize: 14 }}>
                Ini adalah area sensitif aplikasi. Harap masukkan kata sandi akun Anda sebelum melanjutkan.
            </Paragraph>

            {serverErrors?.password && (
                <Alert
                    message={serverErrors.password}
                    type="error"
                    showIcon
                    style={{ marginBottom: 20 }}
                />
            )}

            <Form
                name="confirm_password"
                layout="vertical"
                onFinish={onFinish}
                requiredMark={true}
                size="large"
                disabled={loading}
            >
                <Form.Item
                    name="password"
                    label="Kata Sandi"
                    rules={[
                        {
                            required: true,
                            message: "Harap masukkan kata sandi Anda!",
                        },
                    ]}
                >
                    <Input.Password
                        id="password"
                        prefix={
                            <LockOutlined
                                style={{ color: token.colorTextSecondary }}
                            />
                        }
                        placeholder="Masukkan kata sandi akun"
                        autoComplete="current-password"
                        autoFocus
                    />
                </Form.Item>

                <Form.Item style={{ marginTop: 24, marginBottom: 0 }}>
                    <Button
                        type="primary"
                        htmlType="submit"
                        loading={loading}
                        block
                    >
                        Konfirmasi
                    </Button>
                </Form.Item>
            </Form>
        </GuestLayout>
    );
}
