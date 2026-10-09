import React, { useState } from "react";
import GuestLayout from "@/Layouts/GuestLayout";
import { Head, Link, router } from "@inertiajs/react";
import { Alert, Button, Flex, Form, Input, Typography, theme } from "antd";
import { ArrowLeftOutlined, MailOutlined } from "@ant-design/icons";

const { Title, Paragraph } = Typography;

export default function ForgotPassword({ status, errors: serverErrors }) {
    const [loading, setLoading] = useState(false);
    const { token } = theme.useToken();

    const onFinish = (values) => {
        setLoading(true);

        router.post(route("password.email"), values, {
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
            <Head title="Lupa Kata Sandi - Foresight Radar" />

            <Title level={3} style={{ marginBottom: 8, color: "#0f172a" }}>
                Lupa Kata Sandi?
            </Title>
            <Paragraph type="secondary" style={{ marginBottom: 24, fontSize: 14 }}>
                Masukkan alamat email yang terdaftar. Kami akan mengirimkan tautan untuk mengatur ulang kata sandi Anda.
            </Paragraph>

            {status && (
                <Alert
                    message={status}
                    type="success"
                    showIcon
                    style={{ marginBottom: 20 }}
                />
            )}

            {serverErrors?.email && (
                <Alert
                    message={serverErrors.email}
                    type="error"
                    showIcon
                    style={{ marginBottom: 20 }}
                />
            )}

            <Form
                name="forgot_password"
                layout="vertical"
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
                                style={{ color: token.colorTextSecondary }}
                            />
                        }
                        placeholder="analis@perusahaan.com"
                        autoComplete="email"
                        autoFocus
                    />
                </Form.Item>

                <Form.Item style={{ marginTop: 24, marginBottom: 16 }}>
                    <Button
                        type="primary"
                        htmlType="submit"
                        loading={loading}
                        block
                    >
                        Kirim Tautan Reset Kata Sandi
                    </Button>
                </Form.Item>

                <Flex justify="center" style={{ marginTop: 12 }}>
                    <Link
                        href={route("login")}
                        style={{
                            fontSize: 14,
                            color: token.colorTextSecondary,
                            display: "inline-flex",
                            alignItems: "center",
                            gap: 6,
                        }}
                    >
                        <ArrowLeftOutlined style={{ fontSize: 12 }} />
                        Kembali ke Halaman Masuk
                    </Link>
                </Flex>
            </Form>
        </GuestLayout>
    );
}
