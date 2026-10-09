import React, { useState } from "react";
import GuestLayout from "@/Layouts/GuestLayout";
import { Head, router } from "@inertiajs/react";
import { Alert, Button, Form, Input, Typography, theme } from "antd";
import { LockOutlined, MailOutlined } from "@ant-design/icons";

const { Title, Paragraph } = Typography;

export default function ResetPassword({ token, email, errors: serverErrors }) {
    const [loading, setLoading] = useState(false);
    const { token: antdToken } = theme.useToken();

    const onFinish = (values) => {
        setLoading(true);

        router.post(
            route("password.store"),
            {
                ...values,
                token: token,
            },
            {
                onError: () => {
                    setLoading(false);
                },
                onFinish: () => {
                    setLoading(false);
                },
            }
        );
    };

    return (
        <GuestLayout>
            <Head title="Atur Ulang Kata Sandi - Foresight Radar" />

            <Title level={3} style={{ marginBottom: 8, color: "#0f172a" }}>
                Atur Ulang Kata Sandi
            </Title>
            <Paragraph type="secondary" style={{ marginBottom: 24, fontSize: 14 }}>
                Silakan masukkan kata sandi baru untuk mengakses akun Anda.
            </Paragraph>

            {serverErrors && Object.keys(serverErrors).length > 0 && (
                <Alert
                    message={Object.values(serverErrors)[0]}
                    type="error"
                    showIcon
                    style={{ marginBottom: 20 }}
                />
            )}

            <Form
                name="reset_password"
                layout="vertical"
                initialValues={{ email: email }}
                onFinish={onFinish}
                requiredMark={true}
                size="large"
                disabled={loading}
            >
                <Form.Item
                    name="email"
                    label="Email Kerja"
                    rules={[
                        {
                            required: true,
                            message: "Harap masukkan email kerja Anda!",
                        },
                        {
                            type: "email",
                            message: "Format email tidak valid!",
                        },
                    ]}
                >
                    <Input
                        id="email"
                        prefix={
                            <MailOutlined
                                style={{ color: antdToken.colorTextSecondary }}
                            />
                        }
                        placeholder="analis@perusahaan.com"
                        autoComplete="username"
                    />
                </Form.Item>

                <Form.Item
                    name="password"
                    label="Kata Sandi Baru"
                    rules={[
                        {
                            required: true,
                            message: "Harap masukkan kata sandi baru Anda!",
                        },
                        {
                            min: 8,
                            message: "Kata sandi minimal 8 karakter!",
                        },
                    ]}
                >
                    <Input.Password
                        id="password"
                        prefix={
                            <LockOutlined
                                style={{ color: antdToken.colorTextSecondary }}
                            />
                        }
                        placeholder="Minimal 8 karakter"
                        autoComplete="new-password"
                        autoFocus
                    />
                </Form.Item>

                <Form.Item
                    name="password_confirmation"
                    label="Konfirmasi Kata Sandi"
                    dependencies={["password"]}
                    rules={[
                        {
                            required: true,
                            message: "Harap konfirmasi kata sandi Anda!",
                        },
                        ({ getFieldValue }) => ({
                            validator(_, value) {
                                if (!value || getFieldValue("password") === value) {
                                    return Promise.resolve();
                                }
                                return Promise.reject(
                                    new Error("Konfirmasi kata sandi tidak cocok!")
                                );
                            },
                        }),
                    ]}
                >
                    <Input.Password
                        id="password_confirmation"
                        prefix={
                            <LockOutlined
                                style={{ color: antdToken.colorTextSecondary }}
                            />
                        }
                        placeholder="Ulangi kata sandi baru"
                        autoComplete="new-password"
                    />
                </Form.Item>

                <Form.Item style={{ marginTop: 28, marginBottom: 0 }}>
                    <Button
                        type="primary"
                        htmlType="submit"
                        loading={loading}
                        block
                    >
                        Simpan Kata Sandi Baru
                    </Button>
                </Form.Item>
            </Form>
        </GuestLayout>
    );
}
